<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Entitlements\EntitlementManager;
use App\Support\Entitlements;
use App\Support\Permissions;

class DocumentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowAny($user, Permissions::DOCUMENTS_VIEW);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->allow($user, Permissions::DOCUMENTS_VIEW, $document);
    }

    /**
     * Downloading is checked separately from viewing the list, because the
     * download route receives a raw id and streams a private file.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->allow($user, Permissions::DOCUMENTS_VIEW, $document);
    }

    /** Uploading needs both the permission and the account entitlement. */
    public function create(User $user): bool
    {
        return $this->allowAny($user, Permissions::DOCUMENTS_MANAGE)
            && app(EntitlementManager::class)->allows(Entitlements::DOCUMENTS_ENABLED);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->allow($user, Permissions::DOCUMENTS_MANAGE, $document)
            && app(EntitlementManager::class)->allows(Entitlements::DOCUMENTS_ENABLED);
    }

    /**
     * Losing the upload entitlement must never remove existing files, so
     * deletion is a permission question only, not an entitlement one.
     */
    public function delete(User $user, Document $document): bool
    {
        return $this->allow($user, Permissions::DOCUMENTS_MANAGE, $document);
    }
}
