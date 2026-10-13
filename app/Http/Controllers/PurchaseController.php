<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRule;
use App\Models\FinancialAccount;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\PropertyPurchaseSeller;
use App\Models\Seller;
use App\Models\SellerPayment;
use App\Services\Approvals\ApprovalManager;
use App\Services\Audit\AuditLogger;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly ApprovalManager $approvals,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PropertyPurchase::class);

        $purchases = PropertyPurchase::query()
            ->with(['property', 'sellerShares.seller'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->whereHas(
                'property',
                fn ($p) => $p->search($term)
            ))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.index', ['purchases' => $purchases]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PropertyPurchase::class);

        return view('purchases.form', [
            'purchase' => new PropertyPurchase(['purchase_date' => now()->toDateString()]),
            'properties' => $this->selectableProperties(),
            'sellers' => Seller::orderBy('name')->get(),
            'selectedProperty' => $request->integer('property_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PropertyPurchase::class);

        $data = $this->validated($request);

        $purchase = DB::transaction(function () use ($data, $request) {
            $purchase = PropertyPurchase::create([
                'property_id' => $data['property_id'],
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => Money::of($data['purchase_price'])->toDecimal(),
                'notes' => $data['notes'] ?? null,
                'status' => PropertyPurchase::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $this->syncSellers($purchase, $data['sellers'] ?? []);

            return $purchase;
        });

        $this->audit->log('purchase.created', $purchase, 'Draft purchase created');

        return redirect()->route('purchases.show', $purchase)
            ->with('status', 'Draft purchase saved. Confirm it to record the cost.');
    }

    public function show(PropertyPurchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $purchase->load([
            'property', 'sellerShares.seller',
            'payments.financialAccount', 'payments.seller',
            'documents.currentVersion', 'approvalRequests.requester',
        ]);

        return view('purchases.show', [
            'purchase' => $purchase,
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'outstanding' => $purchase->outstandingPayable(),
            'paid' => $purchase->paidTotal(),
            'needsApproval' => $this->approvals->isRequired(
                ApprovalRule::ACTION_PROPERTY_PURCHASE,
                Money::of($purchase->purchase_price)
            ),
        ]);
    }

    public function edit(PropertyPurchase $purchase): View
    {
        $this->authorize('update', $purchase);

        $purchase->load('sellerShares');

        return view('purchases.form', [
            'purchase' => $purchase,
            'properties' => $this->selectableProperties($purchase->property_id),
            'sellers' => Seller::orderBy('name')->get(),
            'selectedProperty' => $purchase->property_id,
        ]);
    }

    public function update(Request $request, PropertyPurchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $data = $this->validated($request, $purchase);

        DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                'property_id' => $data['property_id'],
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => Money::of($data['purchase_price'])->toDecimal(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncSellers($purchase, $data['sellers'] ?? []);
        });

        return redirect()->route('purchases.show', $purchase)->with('status', 'Draft purchase updated.');
    }

    /**
     * Confirming is what turns the agreed price into recorded cost. If the
     * account holder has switched approval on for purchases, it waits.
     */
    public function confirm(Request $request, PropertyPurchase $purchase): RedirectResponse
    {
        $this->authorize('confirm', $purchase);

        $amount = Money::of($purchase->purchase_price);

        if ($this->approvals->isRequired(ApprovalRule::ACTION_PROPERTY_PURCHASE, $amount)) {
            try {
                $this->approvals->assertApproved(ApprovalRule::ACTION_PROPERTY_PURCHASE, $purchase, $amount);
            } catch (RuntimeException) {
                $this->approvals->request(
                    ApprovalRule::ACTION_PROPERTY_PURCHASE,
                    $purchase,
                    $amount,
                    $request->string('note')->toString() ?: null,
                );

                $purchase->update(['status' => PropertyPurchase::STATUS_PENDING_APPROVAL]);

                return back()->with('status', 'This purchase needs approval. A request has been sent and nothing has been posted yet.');
            }
        }

        try {
            $this->purchases->confirm($purchase);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Purchase confirmed. The price is now recorded as property cost, once.');
    }

    public function storePayment(Request $request, PropertyPurchase $purchase): RedirectResponse
    {
        $this->authorize('recordPayment', $purchase);

        $data = $request->validate([
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->purchases->recordPayment($purchase, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Payment recorded. It reduces the outstanding payable and does not add to the property cost.');
    }

    public function reversePayment(Request $request, SellerPayment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->purchases->reversePayment($payment, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Payment reversed. The original entry has been kept and a linked reversal added.');
    }

    /** @param list<array{seller_id?:int,amount?:string}> $rows */
    private function syncSellers(PropertyPurchase $purchase, array $rows): void
    {
        $purchase->sellerShares()->delete();

        foreach ($rows as $row) {
            if (empty($row['seller_id']) || ! isset($row['amount'])) {
                continue;
            }

            PropertyPurchaseSeller::create([
                'property_purchase_id' => $purchase->id,
                'seller_id' => $row['seller_id'],
                'amount' => Money::of($row['amount'])->toDecimal(),
            ]);
        }
    }

    private function selectableProperties(?int $include = null)
    {
        return Property::query()
            ->whereDoesntHave('purchase')
            ->when($include, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('reference')
            ->get();
    }

    private function validated(Request $request, ?PropertyPurchase $purchase = null): array
    {
        return $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sellers' => ['array'],
            'sellers.*.seller_id' => ['nullable', 'exists:sellers,id'],
            'sellers.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
