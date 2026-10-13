<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Break-glass access to a customer workspace.
 *
 * A Super Admin has no standing access to customer business data. Looking at
 * it requires creating a grant with a written reason and an expiry, and every
 * page viewed under that grant is written to the customer audit log, where
 * the account holder can see it.
 */
class SupportAccessController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:15', 'max:500'],
            'minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);

        $grant = SupportAccessGrant::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'granted_at' => now(),
            'expires_at' => now()->addMinutes($data['minutes']),
        ]);

        $request->session()->put('support_access_tenant_id', $tenant->id);

        $this->audit->log(
            action: 'support_access.granted',
            subject: $grant,
            description: sprintf('Support access to %s for %d minutes', $tenant->name, $data['minutes']),
            reason: $data['reason'],
            tenantId: $tenant->id,
        );

        return redirect()->route('dashboard')
            ->with('status', 'Support access started. It expires at '.$grant->expires_at->format('H:i').' and every page you open is logged for the customer.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $tenantId = $request->session()->pull('support_access_tenant_id');

        if ($tenantId) {
            SupportAccessGrant::where('tenant_id', $tenantId)
                ->where('user_id', $request->user()->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->audit->log(
                action: 'support_access.ended',
                description: 'Support access ended',
                tenantId: $tenantId,
            );
        }

        return redirect()->route('admin.accounts.index')->with('status', 'Support access ended.');
    }
}
