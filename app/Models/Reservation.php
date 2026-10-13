<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Reservation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id', 'property_id', 'buyer_id', 'reservation_date', 'token_amount',
        'agreed_price', 'expiry_date', 'status', 'cancellation_date',
        'cancellation_reason', 'forfeited_amount', 'refunded_amount', 'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'expiry_date' => 'date',
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

    public function payments(): HasMany
    {
        return $this->hasMany(BuyerPayment::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->isActive()
            && $this->expiry_date !== null
            && $this->expiry_date->isPast();
    }

    /**
     * Cash actually received against the reservation. Held as a buyer advance
     * (a liability) until a sale is finalised, so it is never profit on its own.
     */
    public function receivedTotal(): Money
    {
        return Money::of(
            $this->payments()
                ->where('status', BuyerPayment::STATUS_CONFIRMED)
                ->whereIn('type', [BuyerPayment::TYPE_TOKEN, BuyerPayment::TYPE_ADVANCE])
                ->sum('amount')
        );
    }

    public function outstandingAgainstPrice(): Money
    {
        if (! $this->agreed_price) {
            return Money::zero();
        }

        return Money::of($this->agreed_price)->minus($this->receivedTotal());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => $this->isExpired() ? 'Expired' : 'Active',
            self::STATUS_CONVERTED => 'Converted to sale',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
            default => ucfirst($this->status),
        };
    }
}
