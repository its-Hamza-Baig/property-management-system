<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Support\Permissions;

class FinancialAccountPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::ACCOUNTS_VIEW);
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->allow($user, Permissions::ACCOUNTS_VIEW, $account);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::ACCOUNTS_MANAGE);
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $this->allow($user, Permissions::ACCOUNTS_MANAGE, $account);
    }

    /** An account that has ever been used keeps its history. */
    public function delete(User $user, FinancialAccount $account): bool
    {
        return $this->allow($user, Permissions::ACCOUNTS_MANAGE, $account)
            && $account->ledgerAccount?->entries()->doesntExist();
    }
}
