<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\Investor;
use App\Models\InvestorProfitPayable;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Investors\AllocationService;
use App\Support\Entitlements;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvestorController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementManager $entitlements,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Investor::class);

        $investors = Investor::query()
            ->when($request->string('q')->toString(), function ($query, $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('cnic', 'like', $like));
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->withCount(['allocations as active_allocations_count' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('investors.index', ['investors' => $investors]);
    }

    public function create(): View
    {
        $this->authorize('create', Investor::class);

        return view('investors.form', ['investor' => new Investor(['status' => Investor::STATUS_ACTIVE])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Investor::class);

        $investor = Investor::create($this->validated($request) + ['created_by' => $request->user()->id]);

        $this->audit->log('investor.created', $investor, $investor->name);

        return redirect()->route('investors.show', $investor)->with('status', 'Investor added.');
    }

    public function show(Investor $investor): View
    {
        $this->authorize('view', $investor);

        $investor->load([
            'allocations.property',
            'profitPayables.sale.property',
            'distributions.financialAccount',
        ]);

        $payables = $investor->profitPayables;

        return view('investors.show', [
            'investor' => $investor,
            'totalCapital' => Money::sum($investor->allocations->where('status', 'active')->pluck('capital_amount')),
            'profitEarned' => Money::sum($payables->pluck('profit_amount')),
            'profitPaid' => Money::sum($payables->pluck('paid_amount')),
            'profitOutstanding' => $investor->outstandingProfitPayable(),
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'portalAllowed' => $this->entitlements->allows(Entitlements::INVESTOR_PORTAL_ENABLED),
        ]);
    }

    public function edit(Investor $investor): View
    {
        $this->authorize('update', $investor);

        return view('investors.form', ['investor' => $investor]);
    }

    public function update(Request $request, Investor $investor): RedirectResponse
    {
        $this->authorize('update', $investor);

        $before = $investor->only(['name', 'email', 'phone', 'cnic', 'address', 'notes']);
        $investor->update($this->validated($request));

        $this->audit->logChange('investor.updated', $investor, $before);

        return redirect()->route('investors.show', $investor)->with('status', 'Investor updated.');
    }

    /**
     * Activate or deactivate. Deactivation is refused while the investor
     * still has capital in a property that has not been sold or settled,
     * and it never removes any history.
     */
    public function setStatus(Request $request, Investor $investor): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:active,inactive']]);

        if ($request->input('status') === Investor::STATUS_INACTIVE) {
            if (! $request->user()->can('deactivate', $investor)) {
                return back()->with('error', 'This investor still has an open investment. Settle, transfer or close it first.');
            }
        } else {
            $this->authorize('update', $investor);
        }

        $investor->update(['status' => $request->input('status')]);

        $this->audit->log('investor.status_changed', $investor, 'Set to '.$request->input('status'));

        return back()->with('status', 'Investor status updated. All history has been kept.');
    }

    /** Give or remove a read-only portal login for this investor. */
    public function setPortalAccess(Request $request, Investor $investor): RedirectResponse
    {
        $this->authorize('managePortalAccess', $investor);

        $enable = $request->boolean('enabled');

        if ($enable) {
            $this->entitlements->enforce(
                $this->entitlements->allows(Entitlements::INVESTOR_PORTAL_ENABLED),
                'The investor portal is not enabled on your plan.',
            );

            $request->validate(['email' => ['required', 'email']]);

            if (! $investor->user_id) {
                $this->entitlements->enforce(
                    $this->entitlements->canAddInvestorPortalUser(),
                    'You have reached the number of investor logins your plan allows.',
                );

                $password = Str::password(14);

                $user = User::create([
                    'tenant_id' => $investor->tenant_id,
                    'name' => $investor->name,
                    'email' => $request->string('email')->toString(),
                    'password' => $password,
                    'role' => User::ROLE_INVESTOR,
                    'investor_id' => $investor->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $investor->update(['user_id' => $user->id, 'portal_enabled' => true, 'email' => $user->email]);

                return back()->with('status', 'Portal access created. Temporary password: '.$password.' (share it securely, it is shown only once).');
            }

            $investor->portalUser->update(['is_active' => true]);
            $investor->update(['portal_enabled' => true]);
        } else {
            $investor->portalUser?->update(['is_active' => false]);
            $investor->update(['portal_enabled' => false]);
        }

        $this->audit->log('investor.portal_access', $investor, $enable ? 'Enabled' : 'Disabled');

        return back()->with('status', 'Investor portal access updated.');
    }

    /** Record capital actually received from the investor. */
    public function recordCapital(Request $request, Investor $investor, AllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $investor);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'received_date' => ['required', 'date'],
            'property_id' => ['nullable', 'exists:properties,id'],
        ]);

        $allocations->recordCapitalReceipt($investor, $data);

        return back()->with('status', 'Capital receipt recorded.');
    }

    /** The investor statement: capital, profit earned, paid and outstanding. */
    public function statement(Investor $investor): View
    {
        $this->authorize('view', $investor);

        $investor->load(['allocations.property', 'distributions.financialAccount']);

        $payables = InvestorProfitPayable::where('investor_id', $investor->id)
            ->with('sale.property')
            ->get();

        return view('investors.statement', [
            'investor' => $investor,
            'payables' => $payables,
            'profitEarned' => Money::sum($payables->pluck('profit_amount')),
            'profitPaid' => Money::sum($payables->pluck('paid_amount')),
            'profitOutstanding' => $investor->outstandingProfitPayable(),
            'totalCapital' => Money::sum($investor->allocations->where('status', 'active')->pluck('capital_amount')),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnic' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
