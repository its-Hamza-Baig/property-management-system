<?php

namespace App\Policies;

use App\Models\PropertyPurchase;
use App\Models\User;
use App\Support\Permissions;

class PropertyPurchasePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::PURCHASES_VIEW);
    }

    public function view(User $user, PropertyPurchase $purchase): bool
    {
        return $this->allow($user, Permissions::PURCHASES_VIEW, $purchase);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::PURCHASES_MANAGE);
    }

    /** A confirmed purchase is financial history and is not edited in place. */
    public function update(User $user, PropertyPurchase $purchase): bool
    {
        return $this->allow($user, Permissions::PURCHASES_MANAGE, $purchase)
            && $purchase->isEditable();
    }

    public function confirm(User $user, PropertyPurchase $purchase): bool
    {
        return $this->allow($user, Permissions::PURCHASES_MANAGE, $purchase)
            && $purchase->isConfirmable();
    }

    public function recordPayment(User $user, PropertyPurchase $purchase): bool
    {
        return $this->allow($user, Permissions::PAYMENTS_RECORD, $purchase)
            && $purchase->isConfirmed();
    }

    public function delete(User $user, PropertyPurchase $purchase): bool
    {
        return $this->allow($user, Permissions::PURCHASES_MANAGE, $purchase)
            && $purchase->isEditable();
    }
}
