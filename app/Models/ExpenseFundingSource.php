<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whose capital an expense represents.
 *
 * Deliberately separate from ExpensePayment, which records who actually handed
 * over the money. An investor can fund an expense the account holder pays, and
 * the reverse, and the two facts must stay independently recorded.
 */
class ExpenseFundingSource extends Model
{
    use BelongsToTenant;

    public const FUNDER_ACCOUNT_HOLDER = 'account_holder';
    public const FUNDER_INVESTOR = 'investor';

    protected $fillable = [
        'tenant_id', 'expense_id', 'funder_type', 'investor_id', 'amount',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function funderLabel(): string
    {
        return $this->funder_type === self::FUNDER_INVESTOR
            ? ($this->investor?->name ?? 'Investor')
            : 'Account holder';
    }
}
