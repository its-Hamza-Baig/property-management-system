<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPurchaseSeller extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_purchase_id', 'seller_id', 'amount',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PropertyPurchase::class, 'property_purchase_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
