<?php

namespace App\Services\Investors;

use App\Models\FinancialAccount;
use App\Models\InvestorDistribution;
use App\Models\InvestorProfitPayable;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paying investors their share.
 *
 * The payout settles the profit payable that finalisation created. It is
 * posted against that liability and never against an expense account, so
 * distributing profit can never turn into a new property cost.
 */
class DistributionService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function pay(InvestorProfitPayable $payable, array $data): InvestorDistribution
    {
        if ($payable->is_owner) {
            throw new RuntimeException('The account holder share stays in owner capital and is not paid out as a distribution.');
        }

        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The amount must be greater than zero.');
        }

        return DB::transaction(function () use ($payable, $data, $amount) {
            InvestorProfitPayable::whereKey($payable->id)->lockForUpdate()->first();

            $outstanding = $payable->fresh()->outstanding();

            if ($outstanding->isNegative() || $outstanding->isZero()) {
                throw new RuntimeException('There is nothing outstanding on this profit share.');
            }

            if ($amount->greaterThan($outstanding)) {
                throw new RuntimeException(sprintf(
                    'That is more than the %s still owed to this investor.',
                    $outstanding->format(),
                ));
            }

            $account = FinancialAccount::findOrFail($data['financial_account_id']);
            $available = $account->currentBalance();

            if ($amount->greaterThan($available)) {
                throw new RuntimeException(sprintf(
                    'The %s account only holds %s. Available cash and calculated profit are not the same thing.',
                    $account->name,
                    $available->format(),
                ));
            }

            $distribution = InvestorDistribution::create([
                'investor_profit_payable_id' => $payable->id,
                'investor_id' => $payable->investor_id,
                'financial_account_id' => $account->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount->toDecimal(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'status' => InvestorDistribution::STATUS_CONFIRMED,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $journal = $this->ledger->post(
                JournalDraft::make(
                    $distribution->payment_date->toDateString(),
                    'Investor profit distribution: '.($payable->investor?->name ?? ''),
                )
                    ->debit(
                        LedgerAccounts::INVESTOR_PROFIT_PAYABLE,
                        $amount,
                        $payable->property_id,
                        $payable->investor_id,
                    )
                    ->credit('cash:'.$account->id, $amount, $payable->property_id, $payable->investor_id)
                    ->for($distribution)
            );

            $distribution->update(['journal_id' => $journal->id]);

            $payable->refreshPaidAmount();

            $this->audit->log(
                action: 'investor.distribution_paid',
                subject: $distribution,
                description: sprintf(
                    '%s paid to %s from %s',
                    $amount->format(),
                    $payable->investor?->name ?? 'investor',
                    $account->name,
                ),
            );

            return $distribution;
        });
    }

    public function reverse(InvestorDistribution $distribution, string $reason): void
    {
        if (! $distribution->isConfirmed()) {
            throw new RuntimeException('Only a confirmed distribution can be reversed.');
        }

        DB::transaction(function () use ($distribution, $reason) {
            $this->ledger->reverse($distribution->journal, $reason);
            $distribution->update(['status' => InvestorDistribution::STATUS_REVERSED]);
            $distribution->payable->refreshPaidAmount();

            $this->audit->log(
                action: 'investor.distribution_reversed',
                subject: $distribution,
                description: Money::of($distribution->amount)->format(),
                reason: $reason,
            );
        });
    }
}
