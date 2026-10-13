<?php

namespace App\Models;

use App\Support\Entitlements as EntitlementKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name', 'slug', 'plan_id', 'status', 'contact_email', 'contact_phone',
        'activated_at', 'suspended_at', 'status_reason', 'reviewed_by',
        'reviewed_at', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_ACCOUNT_HOLDER);
    }

    public function entitlementOverrides(): HasMany
    {
        return $this->hasMany(TenantEntitlement::class);
    }

    public function supportAccessGrants(): HasMany
    {
        return $this->hasMany(SupportAccessGrant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Can this workspace be used right now? */
    public function canOperate(): bool
    {
        return $this->isActive() && $this->deleted_at === null;
    }

    /**
     * Effective entitlements: catalogue defaults, overlaid with the plan,
     * overlaid with any per-account override set by the Super Admin.
     *
     * @return array<string, mixed>
     */
    public function effectiveEntitlements(): array
    {
        $values = EntitlementKeys::defaults();

        foreach ($this->plan?->limits ?? [] as $key => $value) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $value;
            }
        }

        foreach ($this->entitlementOverrides as $override) {
            if (array_key_exists($override->key, $values)) {
                $values[$override->key] = $override->value;
            }
        }

        foreach ($values as $key => $value) {
            $values[$key] = EntitlementKeys::cast($key, $value);
        }

        return $values;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_VERIFICATION => 'Awaiting email verification',
            self::STATUS_PENDING_APPROVAL => 'Awaiting approval',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CLOSED => 'Closed',
            default => ucfirst($this->status),
        };
    }
}
