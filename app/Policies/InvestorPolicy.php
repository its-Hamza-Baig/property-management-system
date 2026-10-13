<?php

namespace App\Policies;

use App\Models\Investor;
use App\Models\User;
use App\Support\Permissions;

class InvestorPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::INVESTORS_VIEW);
    }

    public function view(User $user, Investor $investor): bool
    {
        // An investor portal user may open their own record and nothing else.
        if ($user->isInvestor()) {
            return $user->investor_id === $investor->id
                && (int) $user->tenant_id === (int) $investor->tenant_id;
        }

        return $this->allow($user, Permissions::INVESTORS_VIEW, $investor);
    }

    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::INVESTORS_MANAGE);
    }

    public function update(User $user, Investor $investor): bool
    {
        return $this->allow($user, Permissions::INVESTORS_MANAGE, $investor);
    }

    /**
     * Investors are never destroyed. Deactivation is the only removal, and it
     * is blocked while capital is still tied up in an unsold property.
     */
    public function deactivate(User $user, Investor $investor): bool
    {
        return $this->allow($user, Permissions::INVESTORS_MANAGE, $investor)
            && ! $investor->hasUnsettledInvestment();
    }

    public function delete(User $user, Investor $investor): bool
    {
        return false;
    }

    public function managePortalAccess(User $user, Investor $investor): bool
    {
        return $this->allow($user, Permissions::INVESTORS_MANAGE, $investor)
            && $user->isAccountHolder();
    }
}
