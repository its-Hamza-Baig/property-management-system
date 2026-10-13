<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class ApprovalRule extends Model
{
    use BelongsToTenant;

    public const ACTION_PROPERTY_PURCHASE = 'property_purchase';
    public const ACTION_SALE_FINALIZATION = 'sale_finalization';
    public const ACTION_EXPENSE = 'expense';
    public const ACTION_REFUND = 'refund';
    public const ACTION_INVESTOR_PAYOUT = 'investor_payout';

    public const ACTIONS = [
        self::ACTION_PROPERTY_PURCHASE => 'Property purchase',
        self::ACTION_SALE_FINALIZATION => 'Sale finalisation',
        self::ACTION_EXPENSE => 'Expense',
        self::ACTION_REFUND => 'Refund to buyer',
        self::ACTION_INVESTOR_PAYOUT => 'Investor profit payout',
    ];

    protected $fillable = ['tenant_id', 'action', 'is_enabled', 'threshold_amount'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /** Does an action of this size need approval under this rule? */
    public function requiresApprovalFor(Money $amount): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->threshold_amount === null) {
            return true;
        }

        return $amount->greaterThanOrEqual($this->threshold_amount);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }
}
