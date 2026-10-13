<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'cnic', 'address', 'notes',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(SellerPayment::class);
    }

    public function purchaseShares(): HasMany
    {
        return $this->hasMany(PropertyPurchaseSeller::class);
    }
}
