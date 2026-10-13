<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Approvals\Approvable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyPurchase extends Model implements Approvable
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'property_id', 'purchase_date', 'purchase_price', 'status',
        'notes', 'created_by', 'confirmed_at', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sellerShares(): HasMany
    {
        return $this->hasMany(PropertyPurchaseSeller::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SellerPayment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /** Ready to be turned into recorded cost. */
    public function isConfirmable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }

    public function markApproved(): void
    {
        if ($this->status === self::STATUS_PENDING_APPROVAL) {
            $this->update(['status' => self::STATUS_APPROVED]);
        }
    }

    public function markRejected(?string $reason = null): void
    {
        if ($this->status === self::STATUS_PENDING_APPROVAL) {
            $this->update(['status' => self::STATUS_REJECTED]);
        }
    }

    /** Confirmed payments made to sellers against this purchase. */
    public function paidTotal(): Money
    {
        return Money::of(
            $this->payments()->where('status', SellerPayment::STATUS_CONFIRMED)->sum('amount')
        );
    }

    /**
     * What is still owed to the sellers. The purchase price was recognised as
     * cost once, when the purchase was confirmed; these payments only reduce
     * the payable and never add cost again.
     */
    public function outstandingPayable(): Money
    {
        if (! $this->isConfirmed()) {
            return Money::zero();
        }

        return Money::of($this->purchase_price)->minus($this->paidTotal());
    }

    /** Amount still owed to one specific seller. */
    public function outstandingForSeller(int $sellerId): Money
    {
        $share = $this->sellerShares()->where('seller_id', $sellerId)->first();

        if (! $share) {
            return Money::zero();
        }

        $paid = Money::of(
            $this->payments()
                ->where('seller_id', $sellerId)
                ->where('status', SellerPayment::STATUS_CONFIRMED)
                ->sum('amount')
        );

        return Money::of($share->amount)->minus($paid);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending approval',
            self::STATUS_APPROVED => 'Approved, ready to confirm',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status),
        };
    }
}
