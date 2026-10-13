<?php

namespace App\Services\Entitlements;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\Entitlements;

/**
 * Reads and enforces the per-account plan limits.
 *
 * A limit never deletes anything. If an account is downgraded, existing data
 * and documents stay exactly as they are; only new actions are refused, with
 * a message that explains which limit was hit.
 */
class EntitlementManager
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function for(?Tenant $tenant = null): array
    {
        $tenant ??= $this->context->tenant();

        if (! $tenant) {
            return Entitlements::defaults();
        }

        return $this->cache[$tenant->id] ??= $tenant->loadMissing(['plan', 'entitlementOverrides'])
            ->effectiveEntitlements();
    }

    public function value(string $key, ?Tenant $tenant = null): mixed
    {
        return $this->for($tenant)[$key] ?? null;
    }

    public function allows(string $key, ?Tenant $tenant = null): bool
    {
        return (bool) $this->value($key, $tenant);
    }

    /** An int limit of -1 means unlimited. */
    public function isUnlimited(string $key, ?Tenant $tenant = null): bool
    {
        return (int) $this->value($key, $tenant) === -1;
    }

    public function remaining(string $key, int $used, ?Tenant $tenant = null): ?int
    {
        if ($this->isUnlimited($key, $tenant)) {
            return null;
        }

        return max(0, ((int) $this->value($key, $tenant)) - $used);
    }

    /** Active (unsold) properties currently counted against the limit. */
    public function propertiesUsed(?Tenant $tenant = null): int
    {
        $tenant ??= $this->context->tenant();

        return Property::query()
            ->forTenant($tenant->id)
            ->activeInventory()
            ->count();
    }

    public function staffUsed(?Tenant $tenant = null): int
    {
        $tenant ??= $this->context->tenant();

        return User::where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_STAFF)
            ->where('is_active', true)
            ->count();
    }

    public function investorPortalUsersUsed(?Tenant $tenant = null): int
    {
        $tenant ??= $this->context->tenant();

        return User::where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_INVESTOR)
            ->where('is_active', true)
            ->count();
    }

    public function canAddProperty(?Tenant $tenant = null): bool
    {
        return $this->isUnlimited(Entitlements::MAX_PROPERTIES, $tenant)
            || $this->propertiesUsed($tenant) < (int) $this->value(Entitlements::MAX_PROPERTIES, $tenant);
    }

    public function canAddStaff(?Tenant $tenant = null): bool
    {
        return $this->isUnlimited(Entitlements::MAX_STAFF, $tenant)
            || $this->staffUsed($tenant) < (int) $this->value(Entitlements::MAX_STAFF, $tenant);
    }

    public function canAddInvestorPortalUser(?Tenant $tenant = null): bool
    {
        if (! $this->allows(Entitlements::INVESTOR_PORTAL_ENABLED, $tenant)) {
            return false;
        }

        return $this->isUnlimited(Entitlements::MAX_INVESTOR_PORTAL_USERS, $tenant)
            || $this->investorPortalUsersUsed($tenant) < (int) $this->value(Entitlements::MAX_INVESTOR_PORTAL_USERS, $tenant);
    }

    /** Throw a readable error the UI turns into a flash message. */
    public function enforce(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new EntitlementException($message);
        }
    }

    public function enforceCanAddProperty(?Tenant $tenant = null): void
    {
        $this->enforce(
            $this->canAddProperty($tenant),
            sprintf(
                'Your plan allows %d active properties and you already have %d. Sold properties do not count. Please contact the administrator to raise this limit.',
                (int) $this->value(Entitlements::MAX_PROPERTIES, $tenant),
                $this->propertiesUsed($tenant),
            ),
        );
    }

    public function enforceCanAddStaff(?Tenant $tenant = null): void
    {
        $this->enforce(
            $this->canAddStaff($tenant),
            sprintf(
                'Your plan allows %d staff accounts and you already have %d active. Please contact the administrator to raise this limit.',
                (int) $this->value(Entitlements::MAX_STAFF, $tenant),
                $this->staffUsed($tenant),
            ),
        );
    }

    public function enforceExports(?Tenant $tenant = null): void
    {
        $this->enforce(
            $this->allows(Entitlements::EXPORTS_ENABLED, $tenant),
            'Excel and PDF export is not enabled on your current plan.',
        );
    }

    public function enforceDocuments(?Tenant $tenant = null): void
    {
        $this->enforce(
            $this->allows(Entitlements::DOCUMENTS_ENABLED, $tenant),
            'Document upload is not enabled for your account. Existing files remain available to download.',
        );
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
