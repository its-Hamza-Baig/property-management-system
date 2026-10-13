<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a login from being useful when the person or the workspace is not
 * allowed to operate: a deactivated staff member, a workspace still waiting
 * for approval, or one that has been suspended or closed.
 *
 * Suspension blocks access. It never deletes anything.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Deactivating a staff member revokes access immediately, including
        // any session that was already open.
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account has been deactivated. Please contact your account administrator.']);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant || $tenant->status !== Tenant::STATUS_ACTIVE) {
            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
