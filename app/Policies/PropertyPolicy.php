<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use App\Support\Permissions;

class PropertyPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::PROPERTIES_VIEW);
    }

    public function view(User $user, Property $property): bool
    {
        if ($user->isInvestor()) {
            // Investors see only properties they actually hold a share in.
            return (int) $user->tenant_id === (int) $property->tenant_id
                && $property->allocations()
                    ->where('investor_id', $user->investor_id)
                    ->exists();
        }

        return $this->allow($user, Permissions::PROPERTIES_VIEW, $property);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::PROPERTIES_MANAGE);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->allow($user, Permissions::PROPERTIES_MANAGE, $property);
    }

    /**
     * A property that carries financial history is never deleted, because the
     * purchase, expenses, investor capital and sale records hang off it.
     */
    public function delete(User $user, Property $property): bool
    {
        if (! $this->allow($user, Permissions::PROPERTIES_MANAGE, $property)) {
            return false;
        }

        return $property->purchase === null
            && $property->sales()->doesntExist()
            && $property->expenses()->doesntExist()
            && $property->allocations()->doesntExist();
    }
}
