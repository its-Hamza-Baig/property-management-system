<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investor extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'cnic', 'address', 'notes',
        'status', 'user_id', 'portal_enabled', 'created_by',
    ];

    protected function casts(): array
    {
        return ['portal_enabled' => 'boolean'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PropertyInvestorAllocation::class);
    }

    public function profitPayables(): HasMany
    {
        return $this->hasMany(InvestorProfitPayable::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(InvestorDistribution::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * An investor with capital still tied up in a property that has not been
     * sold, settled or transferred may not be deactivated.
     */
    public function hasUnsettledInvestment(): bool
    {
        return $this->allocations()
            ->where('status', PropertyInvestorAllocation::STATUS_ACTIVE)
            ->whereNull('effective_to')
            ->whereHas('property', function ($query) {
                $query->where('status', '!=', Property::STATUS_SOLD);
            })
            ->exists();
    }

    /** Unpaid profit still owed to this investor across all finalised sales. */
    public function outstandingProfitPayable(): Money
    {
        return Money::sum(
            $this->profitPayables()->get()->map(
                fn (InvestorProfitPayable $payable) => $payable->outstanding()
            )
        );
    }
}
