<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;

/**
 * Holds the workspace the current request is acting inside.
 *
 * Resolved once per request from the authenticated user. A Super Admin has no
 * workspace of their own and therefore sees no tenant business data at all,
 * unless they impersonate a tenant through an explicit SupportAccessGrant,
 * which is time-boxed, reasoned and written to the audit log.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $isSupportAccess = false;

    public function set(Tenant $tenant, bool $viaSupportAccess = false): void
    {
        $this->tenant = $tenant;
        $this->isSupportAccess = $viaSupportAccess;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->isSupportAccess = false;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    /** True when a Super Admin is inside a workspace under a support grant. */
    public function isSupportAccess(): bool
    {
        return $this->isSupportAccess;
    }

    /**
     * Run a callback inside another workspace and restore the previous one
     * afterwards. Used by the scheduler and by platform-wide reports.
     */
    public function runFor(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousSupport = $this->isSupportAccess;

        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->isSupportAccess = $previousSupport;
        }
    }
}
