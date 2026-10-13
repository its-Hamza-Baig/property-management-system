<?php

namespace App\Services\Cash;

use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Open a cash or bank account, giving it its own ledger account and
     * posting the opening balance against owner capital so the books start
     * out balanced.
     */
    public function openAccount(array $data): FinancialAccount
    {
        return DB::transaction(function () use ($data) {
            $account = FinancialAccount::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'opening_balance' => Money::of($data['opening_balance'] ?? 0)->toDecimal(),
                'opening_date' => $data['opening_date'] ?? now()->toDateString(),
                'is_active' => true,
            ]);

            $this->ledger->createAccountFor($account);

            $opening = Money::of($account->opening_balance);

            if (! $opening->isZero()) {
                $this->ledger->post(
                    JournalDraft::make(
                        $account->opening_date->toDateString(),
                        'Opening balance: '.$account->name,
                    )
                        ->debit('cash:'.$account->id, $opening)
                        ->credit(LedgerAccounts::OWNER_CAPITAL, $opening)
                        ->for($account)
                );
            }

            return $account;
        });
    }

    /**
     * Move money between two accounts the workspace owns.
     *
     * The journal touches two asset accounts and nothing else, so a transfer
     * can never be read as income or as an expense. Depositing cash into the
     * bank is one event here, not a second sale.
     */
    public function transfer(array $data): AccountTransfer
    {
        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The transfer amount must be greater than zero.');
        }

        if ((int) $data['from_financial_account_id'] === (int) $data['to_financial_account_id']) {
            throw new RuntimeException('Choose two different accounts for a transfer.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $from = FinancialAccount::findOrFail($data['from_financial_account_id']);
            $to = FinancialAccount::findOrFail($data['to_financial_account_id']);

            $available = $from->currentBalance();

            if ($amount->greaterThan($available)) {
                throw new RuntimeException(sprintf(
                    'The %s account only holds %s.',
                    $from->name,
                    $available->format(),
                ));
            }

            $transfer = AccountTransfer::create([
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'transfer_date' => $data['transfer_date'],
                'amount' => $amount->toDecimal(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $journal = $this->ledger->post(
                JournalDraft::make(
                    $transfer->transfer_date->toDateString(),
                    sprintf('Transfer: %s to %s', $from->name, $to->name),
                )
                    ->debit('cash:'.$to->id, $amount)
                    ->credit('cash:'.$from->id, $amount)
                    ->for($transfer)
            );

            $transfer->update(['journal_id' => $journal->id]);

            return $transfer;
        });
    }
}
