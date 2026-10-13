<?php

namespace App\Support;

/**
 * The fixed chart of accounts every workspace is seeded with.
 *
 * Every financial fact in the system is a balanced journal against these
 * accounts. Because each concept the client asked to keep separate has its own
 * account, the double-counting rules become arithmetic rather than discipline:
 *
 *  - Paying a seller moves SELLER_PAYABLE to cash and never touches
 *    PROPERTY_COST again, so purchase cost is recognised exactly once.
 *  - A transfer debits one cash account and credits another, touching no
 *    income or expense account, so it can never read as a sale or a cost.
 *  - A buyer token credits BUYER_ADVANCE (a liability), so cash received
 *    before finalisation is never profit.
 *  - Paying an investor debits INVESTOR_PROFIT_PAYABLE, never an expense
 *    account, so a distribution can never become a property cost.
 */
class LedgerAccounts
{
    // Assets
    public const PROPERTY_COST = 'property_cost';
    public const BUYER_RECEIVABLE = 'buyer_receivable';

    // Liabilities
    public const SELLER_PAYABLE = 'seller_payable';
    public const EXPENSE_PAYABLE = 'expense_payable';
    public const BUYER_ADVANCE = 'buyer_advance';
    public const INVESTOR_PROFIT_PAYABLE = 'investor_profit_payable';

    // Equity
    public const INVESTOR_CAPITAL = 'investor_capital';
    public const OWNER_CAPITAL = 'owner_capital';
    public const RETAINED_RESULT = 'retained_result';

    // Income and expense
    public const SALE_REVENUE = 'sale_revenue';
    public const COST_OF_PROPERTIES_SOLD = 'cost_of_properties_sold';
    public const SALE_EXPENSE = 'sale_expense';
    public const FORFEITURE_INCOME = 'forfeiture_income';
    public const LATE_FEE_INCOME = 'late_fee_income';

    /**
     * code => [name, type, system_key]
     *
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function definitions(): array
    {
        return [
            '1100' => ['Property Cost (Inventory)', 'asset', self::PROPERTY_COST],
            '1200' => ['Buyer Receivable', 'asset', self::BUYER_RECEIVABLE],
            '2000' => ['Seller Payable', 'liability', self::SELLER_PAYABLE],
            '2100' => ['Expense Payable', 'liability', self::EXPENSE_PAYABLE],
            '2200' => ['Buyer Advance', 'liability', self::BUYER_ADVANCE],
            '2300' => ['Investor Profit Payable', 'liability', self::INVESTOR_PROFIT_PAYABLE],
            '3000' => ['Investor Capital', 'equity', self::INVESTOR_CAPITAL],
            '3100' => ['Owner Capital', 'equity', self::OWNER_CAPITAL],
            '3900' => ['Retained Result', 'equity', self::RETAINED_RESULT],
            '4000' => ['Sale Revenue', 'income', self::SALE_REVENUE],
            '4100' => ['Forfeiture and Cancellation Income', 'income', self::FORFEITURE_INCOME],
            '4200' => ['Late Fee Income', 'income', self::LATE_FEE_INCOME],
            '5000' => ['Cost of Properties Sold', 'expense', self::COST_OF_PROPERTIES_SOLD],
            '5100' => ['Sale Expenses', 'expense', self::SALE_EXPENSE],
        ];
    }

    /**
     * Account types whose balance is debit-positive. Used to turn raw
     * debit/credit sums into a signed balance a human can read.
     *
     * @return list<string>
     */
    public static function debitPositiveTypes(): array
    {
        return ['asset', 'expense'];
    }

    /** Cash and bank accounts are numbered from here upward. */
    public const CASH_CODE_BASE = 1000;

    /** Per-category expense accounts are numbered from here upward. */
    public const EXPENSE_CODE_BASE = 5200;
}
