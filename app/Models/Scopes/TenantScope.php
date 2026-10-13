<?php

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the current workspace.
 *
 * This is the first of three layers of isolation. The other two are the model
 * policies (checked on every controller action, including downloads, exports
 * and dropdown endpoints) and the explicit tenant check in TenantContext.
 * A query that escapes this scope still has to get past a policy.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->hasTenant()) {
            // No workspace in context (console, platform admin screens, or an
            // unauthenticated request). Returning nothing is the safe default:
            // code that legitimately crosses tenants must say so explicitly by
            // calling withoutTenantScope().
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.tenant_id', $context->tenantId());
    }
}
