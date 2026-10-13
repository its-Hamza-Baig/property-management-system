<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared tenant check for every workspace policy.
 *
 * The global query scope already hides other workspaces, but a policy check
 * runs on the object in hand, which is what protects routes that receive an
 * id directly: downloads, exports, dependent dropdowns and AJAX endpoints.
 * Both layers have to agree before anything is shown.
 */
abstract class BasePolicy
{
    protected function sameTenant(User $user, Model $model): bool
    {
        $tenantId = $model->getAttribute('tenant_id');

        if ($tenantId === null) {
            return false;
        }

        // Break-glass support access: a Super Admin may read inside exactly
        // the workspace their live grant names, and nothing else. The
        // read-only part is enforced by hasPermission().
        if ($user->isSuperAdmin()) {
            $context = app(TenantContext::class);

            return $context->isSupportAccess() && (int) $context->tenantId() === (int) $tenantId;
        }

        return $user->tenant_id !== null && (int) $user->tenant_id === (int) $tenantId;
    }

    /** Workspace user, correct workspace, and holds the permission. */
    protected function allow(User $user, string $permission, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasPermission($permission);
    }

    /** For index and create actions, where there is no model instance yet. */
    protected function allowAny(User $user, string $permission): bool
    {
        return $this->inWorkspace($user) && $user->hasPermission($permission);
    }

    /** Is this user currently operating inside a workspace at all? */
    protected function inWorkspace(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return true;
        }

        return $user->isSuperAdmin() && app(TenantContext::class)->isSupportAccess();
    }
}
