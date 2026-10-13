<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use App\Notifications\ApprovalRequested;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The configurable approval workflow.
 *
 * Approval is off unless the account holder turns it on for a given action,
 * so routine entry is not slowed down. Where it is on, the financial
 * operation cannot complete until somebody other than the requester has
 * approved it.
 */
class ApprovalManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function isRequired(string $action, Money $amount): bool
    {
        $rule = ApprovalRule::where('action', $action)->first();

        return $rule !== null && $rule->requiresApprovalFor($amount);
    }

    /** Raise a request and notify everyone who can decide it. */
    public function request(string $action, Model $subject, Money $amount, ?string $note = null): ApprovalRequest
    {
        $existing = ApprovalRequest::where('approvable_type', $subject->getMorphClass())
            ->where('approvable_id', $subject->getKey())
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($action, $subject, $amount, $note) {
            $request = ApprovalRequest::create([
                'action' => $action,
                'approvable_type' => $subject->getMorphClass(),
                'approvable_id' => $subject->getKey(),
                'amount' => $amount->toDecimal(),
                'requested_by' => auth()->id(),
                'request_note' => $note,
                'status' => ApprovalRequest::STATUS_PENDING,
            ]);

            $this->notifyApprovers($request);

            $this->audit->log(
                action: 'approval.requested',
                subject: $request,
                description: $request->actionLabel().' '.$amount->format(),
            );

            return $request;
        });
    }

    public function approve(ApprovalRequest $request, User $approver, ?string $reason = null): ApprovalRequest
    {
        return $this->decide($request, $approver, ApprovalRequest::STATUS_APPROVED, $reason);
    }

    public function reject(ApprovalRequest $request, User $approver, string $reason): ApprovalRequest
    {
        return $this->decide($request, $approver, ApprovalRequest::STATUS_REJECTED, $reason);
    }

    private function decide(ApprovalRequest $request, User $approver, string $status, ?string $reason): ApprovalRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('This request has already been decided.');
        }

        // Enforced here as well as in the policy, so a service call made from
        // anywhere still cannot self-approve.
        if ($request->requested_by === $approver->id) {
            throw new RuntimeException('You cannot approve your own request.');
        }

        if (! $approver->hasPermission(Permissions::APPROVALS_DECIDE)) {
            throw new RuntimeException('You do not have permission to decide approval requests.');
        }

        return DB::transaction(function () use ($request, $approver, $status, $reason) {
            $request->update([
                'status' => $status,
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            // Move the underlying record on, so an approved operation can
            // actually be completed rather than staying stuck as pending.
            $subject = $request->approvable;

            if ($subject instanceof Approvable) {
                $status === ApprovalRequest::STATUS_APPROVED
                    ? $subject->markApproved()
                    : $subject->markRejected($reason);
            }

            $requester = $request->requester;

            if ($requester) {
                // A notification only tells somebody what happened. It never
                // creates or changes a financial record itself.
                $requester->notify(new ApprovalDecided($request));
            }

            $this->audit->log(
                action: 'approval.'.$status,
                subject: $request,
                description: $request->actionLabel(),
                reason: $reason,
            );

            return $request->refresh();
        });
    }

    /**
     * Guard used by the financial services: refuse to complete an operation
     * that still needs a decision.
     */
    public function assertApproved(string $action, Model $subject, Money $amount): void
    {
        if (! $this->isRequired($action, $amount)) {
            return;
        }

        $request = ApprovalRequest::where('approvable_type', $subject->getMorphClass())
            ->where('approvable_id', $subject->getKey())
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->latest('id')
            ->first();

        if (! $request) {
            throw new RuntimeException('This action needs approval before it can be completed.');
        }
    }

    private function notifyApprovers(ApprovalRequest $request): void
    {
        $tenantId = $this->context->tenantId();

        $approvers = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ACCOUNT_HOLDER, User::ROLE_STAFF])
            ->where('id', '!=', $request->requested_by)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permissions::APPROVALS_DECIDE));

        foreach ($approvers as $approver) {
            $approver->notify(new ApprovalRequested($request));
        }
    }

    /** Seed the rule rows so the settings screen has something to show. */
    public function ensureRules(int $tenantId): void
    {
        foreach (array_keys(ApprovalRule::ACTIONS) as $action) {
            ApprovalRule::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenantId, 'action' => $action],
                ['is_enabled' => false, 'threshold_amount' => null],
            );
        }
    }
}
