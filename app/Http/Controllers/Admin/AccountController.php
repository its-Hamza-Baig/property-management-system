<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Entitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = Tenant::query()
            ->with('plan')
            ->withCount(['users'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), function ($query, $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_email', 'like', $like));
            })
            ->orderByRaw("FIELD(status, 'pending_approval', 'active', 'suspended', 'pending_verification', 'rejected', 'closed')")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounts.index', ['tenants' => $tenants]);
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['plan', 'users', 'entitlementOverrides', 'supportAccessGrants.user']);

        return view('admin.accounts.show', [
            'tenant' => $tenant,
            'plans' => Plan::where('is_active', true)->orderBy('name')->get(),
            'definitions' => Entitlements::definitions(),
            'effective' => $tenant->effectiveEntitlements(),
            'overrides' => $tenant->entitlementOverrides->pluck('value', 'key'),
            // Operational counts only. No financial figures are shown here.
            'propertiesActive' => Property::withoutTenantScope()
                ->where('tenant_id', $tenant->id)->activeInventory()->count(),
            'propertiesTotal' => Property::withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
            'staffActive' => User::where('tenant_id', $tenant->id)
                ->where('role', User::ROLE_STAFF)->where('is_active', true)->count(),
            'activeGrant' => $tenant->supportAccessGrants
                ->first(fn ($grant) => $grant->isUsable()),
        ]);
    }

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['nullable', 'exists:plans,id']]);

        $tenant->update([
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $data['plan_id'] ?? $tenant->plan_id ?? Plan::default()?->id,
            'activated_at' => now(),
            'suspended_at' => null,
            'status_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Idempotent: chart of accounts, categories and approval rules.
        $this->provisioner->provision($tenant->fresh());

        $this->audit->log(
            action: 'account.activated',
            subject: $tenant,
            description: 'Account activated on plan '.($tenant->fresh()->plan?->name ?? 'none'),
            tenantId: $tenant->id,
        );

        return back()->with('status', 'Account activated and set up.');
    }

    public function reject(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $tenant->update([
            'status' => Tenant::STATUS_REJECTED,
            'status_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log('account.rejected', $tenant, 'Request rejected', reason: $data['reason'], tenantId: $tenant->id);

        return back()->with('status', 'Account request rejected.');
    }

    /** Suspension blocks access. It never deletes business data. */
    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $tenant->update([
            'status' => Tenant::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'status_reason' => $data['reason'],
        ]);

        $this->audit->log('account.suspended', $tenant, 'Account suspended', reason: $data['reason'], tenantId: $tenant->id);

        return back()->with('status', 'Account suspended. All records and history have been kept.');
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'status' => Tenant::STATUS_ACTIVE,
            'suspended_at' => null,
            'status_reason' => null,
        ]);

        $this->audit->log('account.reactivated', $tenant, 'Account reactivated', tenantId: $tenant->id);

        return back()->with('status', 'Account reactivated.');
    }

    /**
     * Per-account overrides on top of the plan.
     *
     * Lowering a limit never removes anything: it only applies to what the
     * account tries to do next.
     */
    public function updateEntitlements(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
            'entitlements' => ['array'],
        ]);

        $before = $tenant->effectiveEntitlements();

        if ($request->filled('plan_id')) {
            $tenant->update(['plan_id' => $request->integer('plan_id')]);
        }

        $planDefaults = $tenant->fresh()->plan?->limits ?? [];

        foreach (Entitlements::definitions() as $key => $definition) {
            $submitted = $request->input('entitlements.'.$key);

            if ($definition['type'] === 'bool') {
                $submitted = $request->boolean('entitlements.'.$key) ? '1' : '0';
            }

            $planValue = $planDefaults[$key] ?? $definition['default'];
            $planValueString = is_bool($planValue) ? ($planValue ? '1' : '0') : (string) $planValue;

            if ($submitted === null || $submitted === '' || (string) $submitted === $planValueString) {
                // Matches the plan, so there is no override to store.
                $tenant->entitlementOverrides()->where('key', $key)->delete();

                continue;
            }

            $tenant->entitlementOverrides()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $submitted],
            );
        }

        $tenant->load('entitlementOverrides', 'plan');

        $this->audit->log(
            action: 'account.entitlements_updated',
            subject: $tenant,
            description: 'Plan and limits changed',
            oldValues: array_map(fn ($v) => is_array($v) ? implode(',', $v) : $v, $before),
            newValues: array_map(fn ($v) => is_array($v) ? implode(',', $v) : $v, $tenant->effectiveEntitlements()),
            tenantId: $tenant->id,
        );

        return back()->with('status', 'Plan and limits saved. Existing data and documents are untouched.');
    }
}
