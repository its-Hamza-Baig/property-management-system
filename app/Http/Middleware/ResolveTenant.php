<?php

namespace App\Http\Middleware;

use App\Models\SupportAccessGrant;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the current workspace into the request.
 *
 * For a normal workspace user that is simply their own tenant. For a Super
 * Admin it is nothing at all, unless they hold a live support grant, in which
 * case entering the workspace is recorded in the audit log.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->tenant_id !== null) {
            $tenant = $user->tenant;

            if ($tenant) {
                $this->context->set($tenant);
            }

            return $next($request);
        }

        if ($user->isSuperAdmin() && $request->session()->has('support_access_tenant_id')) {
            $this->applySupportAccess($request, (int) $request->session()->get('support_access_tenant_id'));
        }

        return $next($request);
    }

    private function applySupportAccess(Request $request, int $tenantId): void
    {
        $grant = SupportAccessGrant::where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $grant) {
            // Expired or revoked: drop straight back to platform-only access.
            $request->session()->forget('support_access_tenant_id');

            return;
        }

        $this->context->set($grant->tenant, viaSupportAccess: true);

        // One entry per page, not per query, so the trail stays readable while
        // still showing exactly what was looked at and when.
        $this->audit->log(
            action: 'support_access.request',
            subject: $grant,
            description: $request->method().' '.$request->path(),
            reason: $grant->reason,
            tenantId: $tenantId,
        );
    }
}
