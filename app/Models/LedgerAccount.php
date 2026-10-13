<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'system_key', 'is_system',
        'financial_account_id', 'expense_category_id',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function isDebitPositive(): bool
    {
        return in_array($this->type, LedgerAccounts::debitPositiveTypes(), true);
    }
}
