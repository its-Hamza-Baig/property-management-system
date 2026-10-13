<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRule;
use App\Models\Buyer;
use App\Models\BuyerPayment;
use App\Models\FinancialAccount;
use App\Models\InvestorProfitPayable;
use App\Models\Property;
use App\Models\Sale;
use App\Services\Approvals\ApprovalManager;
use App\Services\Investors\DistributionService;
use App\Services\Sales\BuyerPaymentService;
use App\Services\Sales\SaleService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly BuyerPaymentService $payments,
        private readonly ApprovalManager $approvals,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->with(['property', 'buyer'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->whereHas('property', fn ($p) => $p->search($term)))
            ->when($request->string('from')->toString(), fn ($q, $d) => $q->whereDate('agreement_date', '>=', $d))
            ->when($request->string('to')->toString(), fn ($q, $d) => $q->whereDate('agreement_date', '<=', $d))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'realizedProfit' => Money::of(Sale::finalized()->sum('realized_profit')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Sale::class);

        return view('sales.form', [
            'sale' => new Sale(['agreement_date' => now()->toDateString()]),
            'properties' => Property::whereIn('status', Property::ACTIVE_STATUSES)->orderBy('reference')->get(),
            'buyers' => Buyer::orderBy('name')->get(),
            'preset' => ['property_id' => $request->integer('property_id')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $data = $this->validated($request);

        $sale = Sale::create([
            'property_id' => $data['property_id'],
            'buyer_id' => $data['buyer_id'],
            'reservation_id' => $data['reservation_id'] ?? null,
            'agreement_date' => $data['agreement_date'],
            'sale_price' => Money::of($data['sale_price'])->toDecimal(),
            'notes' => $data['notes'] ?? null,
            'status' => Sale::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        // Taking the property off the market while the sale is in progress.
        $sale->property->update(['status' => Property::STATUS_UNDER_SALE]);

        return redirect()->route('sales.show', $sale)->with('status', 'Draft sale created.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load([
            'property', 'buyer', 'reservation',
            'installments', 'payments.financialAccount',
            'expenses.category', 'profitPayables.investor', 'profitPayables.distributions',
            'documents.currentVersion', 'approvalRequests.requester',
        ]);

        return view('sales.show', [
            'sale' => $sale,
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'received' => $sale->receivedTotal(),
            'outstanding' => $sale->outstandingReceivable(),
            'advanceHeld' => $this->sales->advanceHeldFor($sale),
            'refundDue' => $this->sales->refundDueFor($sale),
            'projectedCost' => $sale->isFinalized() ? Money::of($sale->allocated_cost) : $sale->property->totalCost(),
            'saleExpenses' => $sale->saleExpenseTotal(),
        ]);
    }

    public function edit(Sale $sale): View
    {
        $this->authorize('update', $sale);

        return view('sales.form', [
            'sale' => $sale,
            'properties' => Property::orderBy('reference')->get(),
            'buyers' => Buyer::orderBy('name')->get(),
            'preset' => [],
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $data = $this->validated($request);

        $sale->update([
            'property_id' => $data['property_id'],
            'buyer_id' => $data['buyer_id'],
            'agreement_date' => $data['agreement_date'],
            'sale_price' => Money::of($data['sale_price'])->toDecimal(),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('sales.show', $sale)->with('status', 'Draft sale updated.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $property = $sale->property;
        $sale->delete();
        $property->update(['status' => Property::STATUS_AVAILABLE]);

        return redirect()->route('sales.index')->with('status', 'Draft sale removed.');
    }

    public function finalize(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('finalize', $sale);

        $amount = Money::of($sale->sale_price);

        if ($this->approvals->isRequired(ApprovalRule::ACTION_SALE_FINALIZATION, $amount)) {
            try {
                $this->approvals->assertApproved(ApprovalRule::ACTION_SALE_FINALIZATION, $sale, $amount);
            } catch (RuntimeException) {
                $this->approvals->request(ApprovalRule::ACTION_SALE_FINALIZATION, $sale, $amount);
                $sale->update(['status' => Sale::STATUS_PENDING_APPROVAL]);

                return back()->with('status', 'Finalising this sale needs approval. A request has been sent and nothing has been posted yet.');
            }
        }

        try {
            $sale = $this->sales->finalize($sale, $request->string('finalized_on')->toString() ?: null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', sprintf(
            'Sale finalised. Realised profit %s. This is an accounting figure; cash received is tracked separately.',
            pkr($sale->realized_profit),
        ));
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('cancel', $sale);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
            'cancellation_date' => ['required', 'date'],
            'forfeited_amount' => ['nullable', 'numeric', 'min:0'],
            'return_to_available' => ['nullable', 'boolean'],
        ]);

        try {
            $this->sales->cancel($sale, $data + ['return_to_available' => $request->boolean('return_to_available')]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Sale cancelled. The record and all its history have been kept.');
    }

    public function correctForm(Sale $sale): View
    {
        $this->authorize('correct', $sale);

        return view('sales.correct', ['sale' => $sale]);
    }

    public function correct(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('correct', $sale);

        $data = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:0.01'],
            'correction_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $this->sales->correct($sale, $data, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('sales.show', $sale)
            ->with('status', 'Correction recorded. The original figures are kept in the audit trail and amounts already paid to investors were not changed.');
    }

    public function updateSchedule(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $data = $request->validate([
            'rows' => ['array'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->payments->setSchedule($sale, $data['rows'] ?? []);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Installment plan saved. A schedule is a plan; it does not move any money by itself.');
    }

    public function storePayment(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('recordPayment', $sale);

        $data = $request->validate([
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'installment_id' => ['nullable', 'exists:installments,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'type' => ['required', 'in:token,advance,installment,final,late_fee,refund'],
            'reference' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->payments->record($data + ['sale_id' => $sale->id, 'buyer_id' => $sale->buyer_id]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Receipt recorded.');
    }

    public function reversePayment(Request $request, BuyerPayment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->payments->reverse($payment, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Receipt reversed with a linked entry.');
    }

    /** Pay an investor their share of a finalised sale. */
    public function payInvestor(Request $request, InvestorProfitPayable $payable, DistributionService $distributions): RedirectResponse
    {
        $this->authorize('update', $payable->sale);

        $data = $request->validate([
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $amount = Money::of($data['amount']);

        if ($this->approvals->isRequired(ApprovalRule::ACTION_INVESTOR_PAYOUT, $amount)) {
            try {
                $this->approvals->assertApproved(ApprovalRule::ACTION_INVESTOR_PAYOUT, $payable, $amount);
            } catch (RuntimeException) {
                $this->approvals->request(ApprovalRule::ACTION_INVESTOR_PAYOUT, $payable, $amount);

                return back()->with('status', 'This payout needs approval. A request has been sent and no money has moved.');
            }
        }

        try {
            $distributions->pay($payable, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Distribution paid. It settles the profit payable and is not recorded as a property expense.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'buyer_id' => ['required', 'exists:buyers,id'],
            'reservation_id' => ['nullable', 'exists:reservations,id'],
            'agreement_date' => ['required', 'date'],
            'sale_price' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
