<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BuyerPayment extends Model
{
    use BelongsToTenant;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REVERSED = 'reversed';

    public const TYPE_TOKEN = 'token';
    public const TYPE_ADVANCE = 'advance';
    public const TYPE_INSTALLMENT = 'installment';
    public const TYPE_FINAL = 'final';
    public const TYPE_LATE_FEE = 'late_fee';
    public const TYPE_REFUND = 'refund';

    public const TYPES = [
        self::TYPE_TOKEN => 'Token / booking amount',
        self::TYPE_ADVANCE => 'Advance',
        self::TYPE_INSTALLMENT => 'Installment',
        self::TYPE_FINAL => 'Final settlement',
        self::TYPE_LATE_FEE => 'Late fee',
        self::TYPE_REFUND => 'Refund to buyer',
    ];

    protected $fillable = [
        'tenant_id', 'sale_id', 'reservation_id', 'buyer_id', 'installment_id',
        'financial_account_id', 'payment_date', 'amount', 'method', 'reference',
        'type', 'credited_to', 'status', 'reversal_of_id', 'journal_id',
        'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['payment_date' => 'date'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
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

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function methodLabel(): string
    {
        return SellerPayment::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
