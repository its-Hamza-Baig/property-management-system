<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a finalised sale calculated for one participant.
 *
 * The calculated share, the amount payable and the amount actually handed over
 * are three separate numbers. This model holds the first two; distributions
 * hold the third. They are never collapsed into one figure.
 */
class InvestorProfitPayable extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'tenant_id', 'sale_id', 'property_id', 'investor_id', 'is_owner',
        'profit_share_percentage', 'profit_amount', 'paid_amount', 'status',
    ];

    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(InvestorDistribution::class);
    }

    public function profit(): Money
    {
        return Money::of($this->profit_amount);
    }

    public function paid(): Money
    {
        return Money::of($this->paid_amount);
    }

    public function outstanding(): Money
    {
        return $this->profit()->minus($this->paid());
    }

    public function isLoss(): bool
    {
        return $this->profit()->isNegative();
    }

    /** Recalculate paid_amount from confirmed distributions only. */
    public function refreshPaidAmount(): void
    {
        $paid = Money::of(
            $this->distributions()
                ->where('status', InvestorDistribution::STATUS_CONFIRMED)
                ->sum('amount')
        );

        $this->paid_amount = $paid->toDecimal();
        $this->status = match (true) {
            $paid->isZero() => self::STATUS_OPEN,
            $paid->greaterThanOrEqual($this->profit_amount) => self::STATUS_PAID,
            default => self::STATUS_PARTIALLY_PAID,
        };

        $this->save();
    }

    public function participantName(): string
    {
        return $this->is_owner ? 'Account holder' : ($this->investor?->name ?? 'Unknown');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'Unpaid',
            self::STATUS_PARTIALLY_PAID => 'Partially paid',
            self::STATUS_PAID => 'Paid',
            default => ucfirst($this->status),
        };
    }
}
