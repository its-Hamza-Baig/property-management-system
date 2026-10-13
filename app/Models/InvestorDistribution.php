<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvestorDistribution extends Model
{
    use BelongsToTenant;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'tenant_id', 'investor_profit_payable_id', 'investor_id',
        'financial_account_id', 'payment_date', 'amount', 'method', 'reference',
        'status', 'reversal_of_id', 'journal_id', 'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['payment_date' => 'date'];
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(InvestorProfitPayable::class, 'investor_profit_payable_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
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
}
