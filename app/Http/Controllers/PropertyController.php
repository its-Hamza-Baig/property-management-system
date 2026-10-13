<?php

namespace App\Http\Controllers;

use App\Models\Investor;
use App\Models\Property;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Investors\AllocationService;
use App\Services\Ledger\LedgerQuery;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PropertyController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementManager $entitlements,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->search($request->string('q')->toString())
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('property_type', $t))
            ->when($request->string('city')->toString(), fn ($q, $c) => $q->where('city', 'like', '%'.$c.'%'))
            ->with('purchase')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('properties.index', [
            'properties' => $properties,
            'activeCount' => Property::query()->activeInventory()->count(),
            'limit' => $this->entitlements->value(\App\Support\Entitlements::MAX_PROPERTIES),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Property::class);

        // Checked here as well as on store, so the limit is explained before
        // the form is filled in rather than after.
        $this->entitlements->enforceCanAddProperty();

        return view('properties.form', ['property' => new Property(['status' => Property::STATUS_AVAILABLE])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);
        $this->entitlements->enforceCanAddProperty();

        $property = Property::create($this->validated($request) + ['created_by' => $request->user()->id]);

        $this->audit->log('property.created', $property, $property->reference);

        return redirect()->route('properties.show', $property)->with('status', 'Property added.');
    }

    public function show(Property $property, LedgerQuery $ledger): View
    {
        $this->authorize('view', $property);

        $property->load([
            'purchase.sellerShares.seller',
            'purchase.payments.financialAccount',
            'expenses.category',
            'sales.buyer',
            'reservations.buyer',
            'allocations.investor',
            'documents.currentVersion',
        ]);

        return view('properties.show', [
            'property' => $property,
            'totalCost' => $property->totalCost(),
            'capitalised' => $property->capitalizedExpenseTotal(),
            'finalizedSale' => $property->sales->firstWhere('status', \App\Models\Sale::STATUS_FINALIZED),
        ]);
    }

    public function edit(Property $property): View
    {
        $this->authorize('update', $property);

        return view('properties.form', ['property' => $property]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $before = $property->only(['reference', 'title', 'city', 'status', 'property_type']);
        $property->update($this->validated($request, $property));

        $this->audit->logChange('property.updated', $property, $before);

        return redirect()->route('properties.show', $property)->with('status', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $property->delete();

        $this->audit->log('property.deleted', $property, $property->reference);

        return redirect()->route('properties.index')
            ->with('status', 'Property removed. Only properties with no financial history can be removed.');
    }

    /** Every ledger movement that touched this property. */
    public function ledger(Request $request, Property $property, LedgerQuery $ledger): View
    {
        $this->authorize('view', $property);

        $filters = array_filter([
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ]);

        return view('properties.ledger', [
            'property' => $property,
            'entries' => $ledger->propertyLedger($property->id, $filters)->paginate(50)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function allocations(Property $property): View
    {
        $this->authorize('view', $property);

        return view('properties.allocations', [
            'property' => $property,
            'allocations' => $property->allocations()->active()->with('investor')->get(),
            'history' => $property->allocations()->whereNotNull('effective_to')->with('investor')->orderByDesc('id')->get(),
            'investors' => Investor::where('status', Investor::STATUS_ACTIVE)->orderBy('name')->get(),
            'transfers' => $property->allocations()->exists()
                ? \App\Models\InvestorShareTransfer::where('property_id', $property->id)->latest('id')->get()
                : collect(),
            'totalCost' => $property->totalCost(),
            'accounts' => \App\Models\FinancialAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateAllocations(Request $request, Property $property, AllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $property);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'rows' => ['array'],
            'rows.*.investor_id' => ['nullable', 'exists:investors,id'],
            'rows.*.capital_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.profit_share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $allocations->allocate(
                $property,
                $data['rows'] ?? [],
                $data['effective_from'],
                $data['reason'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('properties.allocations', $property)
            ->with('status', 'Investor allocations saved. The previous version has been kept in the history.');
    }

    public function transferShare(Request $request, Property $property, AllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $property);

        $data = $request->validate([
            'from_investor_id' => ['nullable', 'exists:investors,id'],
            'to_investor_id' => ['nullable', 'exists:investors,id'],
            'transfer_date' => ['required', 'date'],
            'ownership_percentage' => ['required', 'numeric', 'min:0.0001', 'max:100'],
            'profit_share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capital_amount' => ['nullable', 'numeric', 'min:0'],
            'settlement_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $allocations->transferShare($property, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('properties.allocations', $property)
            ->with('status', 'Share transferred. This is recorded as a transfer between parties, not as a property sale.');
    }

    private function validated(Request $request, ?Property $property = null): array
    {
        return $request->validate([
            'reference' => [
                'required', 'string', 'max:64',
                Rule::unique('properties', 'reference')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->ignore($property?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'block' => ['nullable', 'string', 'max:64'],
            'plot_number' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:128'],
            'address' => ['nullable', 'string', 'max:255'],
            'property_type' => ['required', Rule::in(array_keys(Property::TYPES))],
            'size' => ['nullable', 'numeric', 'min:0'],
            'size_unit' => ['nullable', 'string', 'max:24'],
            'status' => ['required', Rule::in([
                Property::STATUS_AVAILABLE,
                Property::STATUS_RESERVED,
                Property::STATUS_UNDER_SALE,
                Property::STATUS_SOLD,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
