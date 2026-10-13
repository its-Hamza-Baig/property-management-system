<?php

namespace App\Services\Ledger;

use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Journal;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Services\Tenancy\TenantContext;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single place where money is written.
 *
 * No controller writes a ledger entry directly. Every financial fact arrives
 * here as a balanced JournalDraft and is persisted inside one transaction, so
 * a half-posted journal cannot exist.
 */
class Ledger
{
    /** @var array<string, int> account cache, keyed by tenant and account key */
    private array $accountCache = [];

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function post(JournalDraft $draft): Journal
    {
        if ($draft->isEmpty()) {
            throw new RuntimeException('Refusing to post a journal with no lines.');
        }

        if (! $draft->balances()) {
            throw new RuntimeException(sprintf(
                'Journal does not balance: debits %s, credits %s (%s).',
                $draft->totalDebit()->toDecimal(),
                $draft->totalCredit()->toDecimal(),
                $draft->memo(),
            ));
        }

        $tenantId = $this->context->tenantId();

        if ($tenantId === null) {
            throw new RuntimeException('Cannot post a journal without a workspace in context.');
        }

        return DB::transaction(function () use ($draft, $tenantId) {
            $journal = Journal::create([
                'tenant_id' => $tenantId,
                'journal_date' => $draft->date(),
                'memo' => $draft->memo(),
                'source_type' => $draft->source() ? $draft->source()->getMorphClass() : null,
                'source_id' => $draft->source()?->getKey(),
                'reversal_of_id' => $draft->reversalOfId(),
                'is_adjustment' => $draft->isAdjustment(),
                'reason' => $draft->reason(),
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($draft->lines() as $line) {
                LedgerEntry::create([
                    'tenant_id' => $tenantId,
                    'journal_id' => $journal->id,
                    'ledger_account_id' => $this->resolveAccountId($line['account']),
                    'debit' => $line['debit']->toDecimal(),
                    'credit' => $line['credit']->toDecimal(),
                    'property_id' => $line['property_id'],
                    'investor_id' => $line['investor_id'],
                    'memo' => $line['memo'],
                    'created_at' => now(),
                ]);
            }

            return $journal->load('entries');
        });
    }

    /**
     * Post the exact mirror image of an existing journal.
     *
     * A reversal never edits or deletes the original. It is a new balanced
     * journal that points back at it, which is what keeps the audit history
     * intact when a confirmed payment has to be undone.
     */
    public function reverse(Journal $journal, string $reason, ?string $date = null): Journal
    {
        if ($journal->isReversed()) {
            throw new RuntimeException('This journal has already been reversed.');
        }

        $draft = JournalDraft::make(
            $date ?? now()->toDateString(),
            'Reversal of: '.$journal->memo,
        )->reversing($journal->id, $reason);

        $journal->loadMissing(['entries', 'source']);

        foreach ($journal->entries as $entry) {
            $accountKey = $this->keyForAccountId($entry->ledger_account_id);

            if (Money::of($entry->debit)->isPositive()) {
                $draft->credit($accountKey, $entry->debit, $entry->property_id, $entry->investor_id, 'Reversal');
            } else {
                $draft->debit($accountKey, $entry->credit, $entry->property_id, $entry->investor_id, 'Reversal');
            }
        }

        if ($journal->source) {
            $draft->for($journal->source);
        }

        return $this->post($draft);
    }

    /**
     * Resolve an account key to its id, creating nothing on the fly: every
     * account must already exist, so a typo fails loudly instead of silently
     * inventing an account that no report knows about.
     */
    public function resolveAccountId(string $key): int
    {
        $tenantId = $this->context->tenantId();
        $cacheKey = $tenantId.':'.$key;

        if (isset($this->accountCache[$cacheKey])) {
            return $this->accountCache[$cacheKey];
        }

        $account = match (true) {
            str_starts_with($key, 'cash:') => LedgerAccount::where(
                'financial_account_id', (int) substr($key, 5)
            )->first(),
            str_starts_with($key, 'expense_category:') => LedgerAccount::where(
                'expense_category_id', (int) substr($key, 17)
            )->first(),
            default => LedgerAccount::where('system_key', $key)->first(),
        };

        if (! $account) {
            throw new RuntimeException("Unknown ledger account: {$key}");
        }

        return $this->accountCache[$cacheKey] = $account->id;
    }

    private function keyForAccountId(int $accountId): string
    {
        $account = LedgerAccount::findOrFail($accountId);

        if ($account->financial_account_id) {
            return 'cash:'.$account->financial_account_id;
        }

        if ($account->expense_category_id) {
            return 'expense_category:'.$account->expense_category_id;
        }

        return $account->system_key;
    }

    /**
     * Create the fixed chart of accounts for a workspace. Idempotent, so it is
     * safe to re-run after a future release adds an account.
     */
    public function seedChartOfAccounts(int $tenantId): void
    {
        foreach (LedgerAccounts::definitions() as $code => [$name, $type, $systemKey]) {
            LedgerAccount::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'system_key' => $systemKey, 'is_system' => true],
            );
        }
    }

    /** Give a cash or bank account its own ledger account. */
    public function createAccountFor(FinancialAccount $account): LedgerAccount
    {
        $code = (string) (LedgerAccounts::CASH_CODE_BASE + $account->id);

        return LedgerAccount::firstOrCreate(
            ['tenant_id' => $account->tenant_id, 'code' => $code],
            [
                'name' => $account->name,
                'type' => 'asset',
                'is_system' => false,
                'financial_account_id' => $account->id,
            ],
        );
    }

    /** Give an expense category its own ledger account. */
    public function createAccountForCategory(ExpenseCategory $category): LedgerAccount
    {
        $code = (string) (LedgerAccounts::EXPENSE_CODE_BASE + $category->id);

        return LedgerAccount::firstOrCreate(
            ['tenant_id' => $category->tenant_id, 'code' => $code],
            [
                'name' => $category->name,
                'type' => 'expense',
                'is_system' => false,
                'expense_category_id' => $category->id,
            ],
        );
    }
}
