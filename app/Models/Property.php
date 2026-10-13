<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_UNDER_SALE = 'under_sale';
    public const STATUS_SOLD = 'sold';

    /** Statuses that count against the max_properties entitlement. */
    public const ACTIVE_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_UNDER_SALE,
    ];

    public const TYPES = [
        'plot' => 'Plot',
        'house' => 'House',
        'apartment' => 'Apartment',
        'shop' => 'Shop',
        'commercial' => 'Commercial property',
        'agricultural' => 'Agricultural land',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'reference', 'title', 'project_name', 'block', 'plot_number',
        'city', 'address', 'property_type', 'size', 'size_unit', 'status', 'notes',
        'created_by',
    ];

    public function purchase(): HasOne
    {
        return $this->hasOne(PropertyPurchase::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PropertyInvestorAllocation::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** The sale that actually closed, if any. */
    public function finalizedSale(): HasOne
    {
        return $this->hasOne(Sale::class)->where('status', Sale::STATUS_FINALIZED);
    }

    /** Sold stock is excluded from active inventory counts and limits. */
    public function scopeActiveInventory(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $like = '%'.$term.'%';
            $inner->where('reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('project_name', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('plot_number', 'like', $like);
        });
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->property_type] ?? ucfirst($this->property_type);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_UNDER_SALE => 'Under sale',
            self::STATUS_SOLD => 'Sold',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'bg-success',
            self::STATUS_RESERVED => 'bg-warning text-dark',
            self::STATUS_UNDER_SALE => 'bg-info text-dark',
            self::STATUS_SOLD => 'bg-secondary',
            default => 'bg-light text-dark',
        };
    }

    /**
     * Purchase price plus every capitalised expense. This is the cost base used
     * for the investor allocation cap and, at finalisation, for realised profit.
     */
    public function totalCost(): Money
    {
        $purchase = $this->purchase;
        $price = $purchase && $purchase->isConfirmed()
            ? Money::of($purchase->purchase_price)
            : Money::zero();

        return $price->plus($this->capitalizedExpenseTotal());
    }

    public function capitalizedExpenseTotal(): Money
    {
        return Money::of(
            $this->expenses()
                ->where('is_capitalized', true)
                ->where('status', Expense::STATUS_CONFIRMED)
                ->sum('amount')
        );
    }
}
