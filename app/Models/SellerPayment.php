<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SellerPayment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REVERSED = 'reversed';

    public const METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'property_purchase_id', 'seller_id', 'financial_account_id',
        'payment_date', 'amount', 'method', 'reference', 'notes', 'status',
        'reversal_of_id', 'journal_id', 'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['payment_date' => 'date'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PropertyPurchase::class, 'property_purchase_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
