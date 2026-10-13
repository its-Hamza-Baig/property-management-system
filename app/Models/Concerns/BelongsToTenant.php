<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model. Scopes all reads and stamps tenant_id
 * on all writes, so a developer cannot forget it by accident.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if ($model->tenant_id !== null) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->hasTenant()) {
                $model->tenant_id = $context->tenantId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Deliberate, readable escape hatch for the few places that must cross
     * workspaces: platform admin counts, the scheduler, and backups. Calling
     * this is a visible decision in a code review.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /** Query a single named tenant, regardless of the current context. */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable().'.tenant_id', $tenantId);
    }
}
