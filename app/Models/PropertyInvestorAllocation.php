<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyInvestorAllocation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXITED = 'exited';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'tenant_id', 'property_id', 'investor_id', 'is_owner', 'capital_amount',
        'contribution_date', 'ownership_percentage', 'profit_share_percentage',
        'status', 'effective_from', 'effective_to', 'agreement_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'contribution_date' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    /** Rows in force right now. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('effective_to');
    }

    /** Rows that were in force on a given date, used when a sale is finalised. */
    public function scopeInForceOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function capital(): Money
    {
        return Money::of($this->capital_amount);
    }

    public function participantName(): string
    {
        return $this->is_owner ? 'Account holder' : ($this->investor?->name ?? 'Unknown');
    }
}
