<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Ledger\LedgerQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';

    protected $fillable = [
        'tenant_id', 'name', 'type', 'bank_name', 'account_number',
        'opening_balance', 'opening_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }

    /**
     * Live balance from the ledger. The opening balance is posted as a journal
     * when the account is created, so it is already included here rather than
     * being added on top by every caller.
     */
    public function currentBalance(): Money
    {
        return app(LedgerQuery::class)->cashBalance($this->id);
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_BANK ? 'Bank' : 'Cash';
    }

    public function displayName(): string
    {
        return $this->type === self::TYPE_BANK && $this->bank_name
            ? $this->name.' ('.$this->bank_name.')'
            : $this->name;
    }
}
