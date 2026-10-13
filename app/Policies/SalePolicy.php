<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;

class SalePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::SALES_VIEW);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_VIEW, $sale);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::SALES_MANAGE);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_MANAGE, $sale)
            && $sale->isEditable();
    }

    public function finalize(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_FINALIZE, $sale)
            && in_array($sale->status, [Sale::STATUS_DRAFT, Sale::STATUS_APPROVED], true);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_CANCEL, $sale)
            && ! $sale->isCancelled();
    }

    /**
     * Correcting a finalised sale is an account-holder decision with a written
     * reason. It never needs Super Admin approval, and it never overwrites the
     * original record.
     */
    public function correct(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_FINALIZE, $sale)
            && $sale->isFinalized();
    }

    public function recordPayment(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::PAYMENTS_RECORD, $sale)
            && ! $sale->isCancelled();
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->allow($user, Permissions::SALES_MANAGE, $sale)
            && $sale->isEditable();
    }
}
