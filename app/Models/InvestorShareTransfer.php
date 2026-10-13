<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorShareTransfer extends Model
{
    use BelongsToTenant;

    public const SETTLEMENT_UNPAID = 'unpaid';
    public const SETTLEMENT_PAID = 'paid';
    public const SETTLEMENT_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'tenant_id', 'property_id', 'from_investor_id', 'to_investor_id',
        'transfer_date', 'capital_amount', 'ownership_percentage',
        'profit_share_percentage', 'settlement_amount', 'settlement_status',
        'financial_account_id', 'reason', 'journal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function fromInvestor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'from_investor_id');
    }

    public function toInvestor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'to_investor_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function fromName(): string
    {
        return $this->fromInvestor?->name ?? 'Account holder';
    }

    public function toName(): string
    {
        return $this->toInvestor?->name ?? 'Account holder';
    }
}
