<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit trail.
 *
 * Used for corrections, cancellations, reversals, approvals, support access,
 * document actions and exports: anything where "who did this, when and why"
 * has to survive.
 */
class AuditLogger
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $tenantId = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenantId ?? $this->context->tenantId(),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /** Record a change with before and after values taken from the model. */
    public function logChange(string $action, Model $subject, array $before, ?string $reason = null, ?string $description = null): AuditLog
    {
        $after = array_intersect_key($subject->getAttributes(), $before);

        return $this->log(
            action: $action,
            subject: $subject,
            description: $description,
            oldValues: $before,
            newValues: $after,
            reason: $reason,
        );
    }
}
