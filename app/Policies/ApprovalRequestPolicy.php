<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions;

class ApprovalRequestPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        if (! $this->sameTenant($user, $request)) {
            return false;
        }

        return $user->hasPermission(Permissions::APPROVALS_DECIDE)
            || $request->requested_by === $user->id;
    }

    /**
     * Deciding requires the permission, a pending request, and not being the
     * person who raised it. Staff do not approve their own work by default.
     */
    public function decide(User $user, ApprovalRequest $request): bool
    {
        if (! $this->allow($user, Permissions::APPROVALS_DECIDE, $request)) {
            return false;
        }

        if (! $request->isPending()) {
            return false;
        }

        return $request->requested_by !== $user->id;
    }

    public function cancel(User $user, ApprovalRequest $request): bool
    {
        return $this->sameTenant($user, $request)
            && $request->isPending()
            && ($request->requested_by === $user->id || $user->isAccountHolder());
    }
}
