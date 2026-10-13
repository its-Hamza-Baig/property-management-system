<?php

namespace App\Services\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every balance shown anywhere in the application is read from here.
 *
 * Because receivables, payables, cash, capital, revenue and profit each have
 * their own ledger account, a report is a filtered sum over one table. Nothing
 * re-derives a total by adding up payment rows, which is exactly the mistake
 * the specification warns about.
 */
class LedgerQuery
{
    /**
     * Signed balance of one account. Positive means the natural direction of
     * the account type: assets and expenses are debit-positive, everything
     * else is credit-positive.
     */
    public function balance(string $accountKey, array $filters = []): Money
    {
        $account = $this->account($accountKey);

        if (! $account) {
            return Money::zero();
        }

        $query = $this->entries($filters)->where('ledger_account_id', $account->id);

        $debit = Money::of((clone $query)->sum('debit'));
        $credit = Money::of((clone $query)->sum('credit'));

        return $account->isDebitPositive()
            ? $debit->minus($credit)
            : $credit->minus($debit);
    }

    /** Current balance of one cash or bank account, including its opening balance. */
    public function cashBalance(int $financialAccountId, array $filters = []): Money
    {
        return $this->balance('cash:'.$financialAccountId, $filters);
    }

    /** Total of every cash and bank account in the workspace. */
    public function totalCash(array $filters = []): Money
    {
        $accountIds = LedgerAccount::whereNotNull('financial_account_id')->pluck('id');

        return $this->sumOver($accountIds->all(), true, $filters);
    }

    /** Total of all per-category expense accounts plus the sale expense account. */
    public function totalExpenses(array $filters = []): Money
    {
        $accountIds = LedgerAccount::whereNotNull('expense_category_id')->pluck('id')->all();

        return $this->sumOver($accountIds, true, $filters);
    }

    /**
     * Net result for the business, read straight from the ledger rather than
     * recomputed: all income less cost of properties sold less every expense
     * recognised in the period.
     *
     * This is a wider figure than the per-sale realised profit stored on each
     * sale, because it also carries holding costs that were never capitalised
     * into a single property. Both numbers are shown, clearly labelled, and
     * neither is ever presented as cash.
     */
    public function netResult(array $filters = []): Money
    {
        return $this->totalIncome($filters)
            ->minus($this->balance(LedgerAccounts::COST_OF_PROPERTIES_SOLD, $filters))
            ->minus($this->totalExpenses($filters));
    }

    public function totalIncome(array $filters = []): Money
    {
        return $this->balance(LedgerAccounts::SALE_REVENUE, $filters)
            ->plus($this->balance(LedgerAccounts::FORFEITURE_INCOME, $filters))
            ->plus($this->balance(LedgerAccounts::LATE_FEE_INCOME, $filters));
    }

    /** Per-property ledger, newest first, for the property financial ledger report. */
    public function propertyLedger(int $propertyId, array $filters = []): Builder
    {
        return $this->entries($filters)
            ->with(['journal', 'ledgerAccount'])
            ->where('property_id', $propertyId)
            ->orderByDesc('id');
    }

    /** @return array<string, Money> keyed by system key */
    public function summary(array $filters = []): array
    {
        return [
            'cash' => $this->totalCash($filters),
            'buyer_receivable' => $this->balance(LedgerAccounts::BUYER_RECEIVABLE, $filters),
            'seller_payable' => $this->balance(LedgerAccounts::SELLER_PAYABLE, $filters),
            'expense_payable' => $this->balance(LedgerAccounts::EXPENSE_PAYABLE, $filters),
            'buyer_advance' => $this->balance(LedgerAccounts::BUYER_ADVANCE, $filters),
            'investor_profit_payable' => $this->balance(LedgerAccounts::INVESTOR_PROFIT_PAYABLE, $filters),
            'investor_capital' => $this->balance(LedgerAccounts::INVESTOR_CAPITAL, $filters),
            'owner_capital' => $this->balance(LedgerAccounts::OWNER_CAPITAL, $filters),
            'property_cost' => $this->balance(LedgerAccounts::PROPERTY_COST, $filters),
            'net_result' => $this->netResult($filters),
        ];
    }

    /**
     * Prove the books balance. Used by the integrity check on the dashboard
     * and by the test suite: if this is ever non-zero something posted an
     * unbalanced journal, which post() is supposed to make impossible.
     */
    public function trialBalanceDifference(): Money
    {
        $debit = Money::of(LedgerEntry::sum('debit'));
        $credit = Money::of(LedgerEntry::sum('credit'));

        return $debit->minus($credit);
    }

    /** @param list<int> $accountIds */
    private function sumOver(array $accountIds, bool $debitPositive, array $filters): Money
    {
        if ($accountIds === []) {
            return Money::zero();
        }

        $query = $this->entries($filters)->whereIn('ledger_account_id', $accountIds);

        $debit = Money::of((clone $query)->sum('debit'));
        $credit = Money::of((clone $query)->sum('credit'));

        return $debitPositive ? $debit->minus($credit) : $credit->minus($debit);
    }

    /**
     * @param  array{from?:string,to?:string,property_id?:int,investor_id?:int}  $filters
     */
    private function entries(array $filters): Builder
    {
        $query = LedgerEntry::query();

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->whereHas('journal', function (Builder $journal) use ($filters) {
                if (! empty($filters['from'])) {
                    $journal->whereDate('journal_date', '>=', $filters['from']);
                }

                if (! empty($filters['to'])) {
                    $journal->whereDate('journal_date', '<=', $filters['to']);
                }
            });
        }

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (! empty($filters['investor_id'])) {
            $query->where('investor_id', $filters['investor_id']);
        }

        return $query;
    }

    private function account(string $key): ?LedgerAccount
    {
        return match (true) {
            str_starts_with($key, 'cash:') => LedgerAccount::where('financial_account_id', (int) substr($key, 5))->first(),
            str_starts_with($key, 'expense_category:') => LedgerAccount::where('expense_category_id', (int) substr($key, 17))->first(),
            default => LedgerAccount::where('system_key', $key)->first(),
        };
    }
}
