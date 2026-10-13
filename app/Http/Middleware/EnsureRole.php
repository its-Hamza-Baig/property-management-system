<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate, used as role:super_admin or role:account_holder,staff.
 *
 * This is coarse routing only. Every individual action is still authorised by
 * a policy or gate inside the controller.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You do not have access to this area.');
        }

        if (! in_array($user->role, $roles, true) && ! $this->isSupportVisit($user, $roles)) {
            abort(403, 'You do not have access to this area.');
        }

        // An investor portal user is only ever allowed in when the account
        // still holds the entitlement and their own access is switched on.
        if ($user->role === User::ROLE_INVESTOR) {
            $investor = $user->investor;

            if (! $investor || ! $investor->portal_enabled) {
                abort(403, 'Investor portal access is not enabled for your account.');
            }
        }

        return $next($request);
    }

    /**
     * A Super Admin inside a live support grant may enter the workspace area.
     * What they can actually do there is read-only, enforced by the policies.
     *
     * @param  list<string>  $roles
     */
    private function isSupportVisit(User $user, array $roles): bool
    {
        return $user->isSuperAdmin()
            && in_array(User::ROLE_ACCOUNT_HOLDER, $roles, true)
            && app(TenantContext::class)->isSupportAccess();
    }
}
