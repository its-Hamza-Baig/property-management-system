<?php

namespace App\Services\Approvals;

/**
 * Implemented by records that can be held up waiting for approval.
 *
 * The approval request tracks the decision; this tells the record itself to
 * move on afterwards, so an approved purchase or sale can actually be
 * completed instead of being stuck in "pending approval" forever.
 */
interface Approvable
{
    public function markApproved(): void;

    public function markRejected(?string $reason = null): void;
}
