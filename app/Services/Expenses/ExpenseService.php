<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FinancialAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Recognise the expense.
     *
     * Recognition and payment are two separate events. This one books the
     * cost and creates a payable; paying it later only clears that payable.
     *
     * A capitalised category increases the cost of the property instead of
     * hitting profit immediately, which is what keeps the investor allocation
     * cap and the eventual profit calculation consistent.
     */
    public function confirm(Expense $expense): Expense
    {
        if ($expense->isConfirmed()) {
            throw new RuntimeException('This expense has already been confirmed.');
        }

        $amount = Money::of($expense->amount);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The expense amount must be greater than zero.');
        }

        $category = $expense->category;

        return DB::transaction(function () use ($expense, $amount, $category) {
            $propertyId = $expense->property_id ?? $expense->sale?->property_id;

            $draft = JournalDraft::make(
                $expense->expense_date->toDateString(),
                'Expense: '.$category->name,
            )->for($expense);

            if ($category->is_capitalized) {
                $draft->debit(LedgerAccounts::PROPERTY_COST, $amount, $propertyId);
            } else {
                $draft->debit('expense_category:'.$category->id, $amount, $propertyId);
            }

            $draft->credit(LedgerAccounts::EXPENSE_PAYABLE, $amount, $propertyId);

            $journal = $this->ledger->post($draft);

            $expense->update([
                'status' => Expense::STATUS_CONFIRMED,
                // Snapshot the flag so a later category edit cannot restate
                // profit that has already been reported.
                'is_capitalized' => $category->is_capitalized,
                'journal_id' => $journal->id,
                'confirmed_at' => now(),
            ]);

            $this->audit->log(
                action: 'expense.confirmed',
                subject: $expense,
                description: $category->name.' '.$amount->format(),
            );

            return $expense->refresh();
        });
    }

    /** Settle an outstanding expense payable from a cash or bank account. */
    public function recordPayment(Expense $expense, array $data): ExpensePayment
    {
        if (! $expense->isConfirmed()) {
            throw new RuntimeException('Confirm the expense before paying it.');
        }

        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($expense, $data, $amount) {
            Expense::whereKey($expense->id)->lockForUpdate()->first();

            $outstanding = $expense->fresh()->outstandingPayable();

            if ($amount->greaterThan($outstanding)) {
                throw new RuntimeException(sprintf(
                    'That payment is more than the outstanding balance of %s.',
                    $outstanding->format(),
                ));
            }

            $account = FinancialAccount::findOrFail($data['financial_account_id']);

            $payment = ExpensePayment::create([
                'expense_id' => $expense->id,
                'financial_account_id' => $account->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount->toDecimal(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'status' => ExpensePayment::STATUS_CONFIRMED,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $propertyId = $expense->property_id ?? $expense->sale?->property_id;

            $journal = $this->ledger->post(
                JournalDraft::make(
                    $payment->payment_date->toDateString(),
                    'Expense payment: '.$expense->category->name,
                )
                    ->debit(LedgerAccounts::EXPENSE_PAYABLE, $amount, $propertyId)
                    ->credit('cash:'.$account->id, $amount, $propertyId)
                    ->for($payment)
            );

            $payment->update(['journal_id' => $journal->id]);

            return $payment;
        });
    }

    /**
     * Cancel a confirmed expense by reversing it. Any payments already made
     * against it have to be reversed first, so cash never goes out of step.
     */
    public function cancel(Expense $expense, string $reason): Expense
    {
        if (! $expense->isConfirmed()) {
            throw new RuntimeException('Only a confirmed expense can be cancelled.');
        }

        if (! $expense->paidTotal()->isZero()) {
            throw new RuntimeException('Reverse the payments against this expense before cancelling it.');
        }

        return DB::transaction(function () use ($expense, $reason) {
            $this->ledger->reverse($expense->journal, $reason);

            $expense->update(['status' => Expense::STATUS_CANCELLED]);

            $this->audit->log(
                action: 'expense.cancelled',
                subject: $expense,
                description: Money::of($expense->amount)->format(),
                reason: $reason,
            );

            return $expense->refresh();
        });
    }

    public function reversePayment(ExpensePayment $payment, string $reason): void
    {
        if (! $payment->isConfirmed()) {
            throw new RuntimeException('Only a confirmed payment can be reversed.');
        }

        DB::transaction(function () use ($payment, $reason) {
            $this->ledger->reverse($payment->journal, $reason);
            $payment->update(['status' => ExpensePayment::STATUS_REVERSED]);

            $this->audit->log(
                action: 'expense_payment.reversed',
                subject: $payment,
                description: Money::of($payment->amount)->format(),
                reason: $reason,
            );
        });
    }
}
