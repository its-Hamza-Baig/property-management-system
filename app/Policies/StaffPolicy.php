<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class StaffPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::STAFF_MANAGE);
    }

    public function view(User $user, User $staff): bool
    {
        return $this->allow($user, Permissions::STAFF_MANAGE, $staff);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::STAFF_MANAGE);
    }

    /** Nobody edits the account holder record through the staff screens. */
    public function update(User $user, User $staff): bool
    {
        return $this->allow($user, Permissions::STAFF_MANAGE, $staff)
            && $staff->isStaff();
    }

    /** Deactivation revokes login but keeps all historical activity. */
    public function deactivate(User $user, User $staff): bool
    {
        return $this->update($user, $staff) && $staff->id !== $user->id;
    }

    public function delete(User $user, User $staff): bool
    {
        return false;
    }
}
