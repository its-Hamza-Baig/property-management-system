<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Support\Permissions;

class ExpensePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::EXPENSES_VIEW);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::EXPENSES_VIEW, $expense);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::EXPENSES_MANAGE);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::EXPENSES_MANAGE, $expense)
            && $expense->isEditable();
    }

    public function confirm(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::EXPENSES_MANAGE, $expense)
            && $expense->isConfirmable();
    }

    public function pay(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::PAYMENTS_RECORD, $expense)
            && $expense->isConfirmed();
    }

    /** Only a draft can be removed. A confirmed expense is cancelled instead. */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::EXPENSES_MANAGE, $expense)
            && $expense->isEditable();
    }

    public function cancel(User $user, Expense $expense): bool
    {
        return $this->allow($user, Permissions::EXPENSES_MANAGE, $expense)
            && $expense->isConfirmed();
    }
}
