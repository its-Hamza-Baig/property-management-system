<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'from_financial_account_id', 'to_financial_account_id',
        'transfer_date', 'amount', 'reference', 'notes', 'journal_id',
        'idempotency_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_financial_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_financial_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
