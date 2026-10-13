<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'tenant_id', 'sale_id', 'installment_no', 'due_date', 'amount',
        'paid_amount', 'late_fee', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BuyerPayment::class);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', now()->toDateString());
    }

    public function remaining(): Money
    {
        return Money::of($this->amount)->minus($this->paid_amount);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PARTIAL], true)
            && $this->due_date->isPast();
    }

    /**
     * Recalculate from the confirmed payments applied to this installment.
     * The schedule row is only ever a reflection of real receipts.
     */
    public function refreshPaidAmount(): void
    {
        $paid = Money::of(
            $this->payments()
                ->where('status', BuyerPayment::STATUS_CONFIRMED)
                ->whereIn('type', [BuyerPayment::TYPE_INSTALLMENT, BuyerPayment::TYPE_FINAL])
                ->sum('amount')
        );

        $this->paid_amount = $paid->toDecimal();

        if ($this->status !== self::STATUS_WAIVED) {
            $this->status = match (true) {
                $paid->isZero() => self::STATUS_PENDING,
                $paid->greaterThanOrEqual($this->amount) => self::STATUS_PAID,
                default => self::STATUS_PARTIAL,
            };
        }

        $this->save();
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PARTIAL => 'Partially paid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_WAIVED => 'Waived',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        if ($this->isOverdue()) {
            return 'bg-danger';
        }

        return match ($this->status) {
            self::STATUS_PAID => 'bg-success',
            self::STATUS_PARTIAL => 'bg-warning text-dark',
            self::STATUS_WAIVED => 'bg-secondary',
            default => 'bg-light text-dark',
        };
    }
}
