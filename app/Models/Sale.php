<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Approvals\Approvable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model implements Approvable
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'property_id', 'buyer_id', 'reservation_id', 'agreement_date',
        'sale_price', 'status', 'finalized_at', 'finalized_by', 'allocated_cost',
        'sale_expense_total', 'realized_profit', 'cancellation_date',
        'cancellation_reason', 'forfeited_amount', 'refunded_amount',
        'supersedes_sale_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'agreement_date' => 'date',
            'finalized_at' => 'datetime',
            'cancellation_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BuyerPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function profitPayables(): HasMany
    {
        return $this->hasMany(InvestorProfitPayable::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_sale_id');
    }

    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
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
            $this->update(['status' => self::STATUS_REJECTED, 'notes' => trim($this->notes."\nRejected: ".$reason)]);
        }
    }

    /**
     * Cash actually received from the buyer. This is NOT the same thing as the
     * sale value and NOT the same thing as profit; all three are reported
     * separately and never substituted for one another.
     */
    public function receivedTotal(): Money
    {
        $received = Money::of(
            $this->payments()
                ->where('status', BuyerPayment::STATUS_CONFIRMED)
                ->whereIn('type', [
                    BuyerPayment::TYPE_TOKEN,
                    BuyerPayment::TYPE_ADVANCE,
                    BuyerPayment::TYPE_INSTALLMENT,
                    BuyerPayment::TYPE_FINAL,
                ])
                ->sum('amount')
        );

        // A token taken at reservation stage belongs to this sale once the
        // reservation has been converted.
        if ($this->reservation_id) {
            $received = $received->plus(
                Money::of(
                    BuyerPayment::where('reservation_id', $this->reservation_id)
                        ->whereNull('sale_id')
                        ->where('status', BuyerPayment::STATUS_CONFIRMED)
                        ->sum('amount')
                )
            );
        }

        return $received->minus($this->refundedTotal());
    }

    public function refundedTotal(): Money
    {
        return Money::of(
            $this->payments()
                ->where('status', BuyerPayment::STATUS_CONFIRMED)
                ->where('type', BuyerPayment::TYPE_REFUND)
                ->sum('amount')
        );
    }

    /** Still owed by the buyer. Never counted as cash. */
    public function outstandingReceivable(): Money
    {
        return Money::of($this->sale_price)->minus($this->receivedTotal());
    }

    /** Confirmed, non-capitalised expenses booked against this sale. */
    public function saleExpenseTotal(): Money
    {
        return Money::of(
            $this->expenses()->where('status', Expense::STATUS_CONFIRMED)->sum('amount')
        );
    }

    public function overdueInstallments(): HasMany
    {
        return $this->installments()
            ->whereIn('status', [Installment::STATUS_PENDING, Installment::STATUS_PARTIAL])
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_FINALIZED => 'Finalised',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_FINALIZED => 'bg-success',
            self::STATUS_APPROVED => 'bg-info text-dark',
            self::STATUS_PENDING_APPROVAL => 'bg-warning text-dark',
            self::STATUS_CANCELLED, self::STATUS_REJECTED => 'bg-danger',
            default => 'bg-secondary',
        };
    }
}
