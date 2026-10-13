<?php

namespace App\Services\Sales;

use App\Models\BuyerPayment;
use App\Models\FinancialAccount;
use App\Models\Installment;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Receipts from buyers.
 *
 * The type of the receipt decides its accounting treatment, and none of them
 * is ever treated as profit:
 *
 *   token / advance  -> buyer advance, a liability, until the sale is final
 *   installment      -> clears the buyer receivable created at finalisation
 *   late fee         -> income in its own right
 *   refund           -> money going back out, reducing the advance held
 */
class BuyerPaymentService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function record(array $data): BuyerPayment
    {
        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The amount must be greater than zero.');
        }

        $type = $data['type'] ?? BuyerPayment::TYPE_INSTALLMENT;

        return DB::transaction(function () use ($data, $amount, $type) {
            $account = FinancialAccount::findOrFail($data['financial_account_id']);
            $sale = ! empty($data['sale_id']) ? Sale::lockForUpdate()->findOrFail($data['sale_id']) : null;
            $reservation = ! empty($data['reservation_id']) ? Reservation::findOrFail($data['reservation_id']) : null;
            $propertyId = $sale?->property_id ?? $reservation?->property_id;

            $this->guardAmount($sale, $type, $amount);

            $installmentId = $data['installment_id'] ?? null;
            $creditedTo = $this->creditAccountFor($sale, $type);

            $payment = BuyerPayment::create([
                'sale_id' => $sale?->id,
                'reservation_id' => $reservation?->id,
                'buyer_id' => $data['buyer_id'] ?? $sale?->buyer_id ?? $reservation?->buyer_id,
                'installment_id' => $installmentId,
                'financial_account_id' => $account->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount->toDecimal(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'type' => $type,
                'credited_to' => $creditedTo,
                'status' => BuyerPayment::STATUS_CONFIRMED,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $journal = $this->ledger->post(
                $this->draftFor($payment, $amount, $type, $creditedTo, $account->id, $propertyId)
            );

            $payment->update(['journal_id' => $journal->id]);

            if ($installmentId) {
                Installment::findOrFail($installmentId)->refreshPaidAmount();
            }

            return $payment;
        });
    }

    /**
     * Which account a receipt of this type settles against.
     *
     * Before finalisation there is no receivable yet, so every receipt is
     * held as an advance; finalisation then moves the lot across in one step.
     * The answer is stored on the payment row so nothing later has to guess.
     */
    private function creditAccountFor(?Sale $sale, string $type): string
    {
        return match (true) {
            $type === BuyerPayment::TYPE_REFUND => LedgerAccounts::BUYER_ADVANCE,
            $type === BuyerPayment::TYPE_LATE_FEE => LedgerAccounts::LATE_FEE_INCOME,
            $sale !== null && $sale->isFinalized() => LedgerAccounts::BUYER_RECEIVABLE,
            default => LedgerAccounts::BUYER_ADVANCE,
        };
    }

    private function draftFor(
        BuyerPayment $payment,
        Money $amount,
        string $type,
        string $creditedTo,
        int $cashAccountId,
        ?int $propertyId,
    ): JournalDraft {
        $draft = JournalDraft::make(
            $payment->payment_date->toDateString(),
            'Buyer '.str_replace('_', ' ', $type),
        )->for($payment);

        // A refund is the only one where money goes out.
        if ($type === BuyerPayment::TYPE_REFUND) {
            return $draft
                ->debit($creditedTo, $amount, $propertyId)
                ->credit('cash:'.$cashAccountId, $amount, $propertyId);
        }

        return $draft
            ->debit('cash:'.$cashAccountId, $amount, $propertyId)
            ->credit($creditedTo, $amount, $propertyId);
    }

    /** Do not let a buyer be recorded as paying more than they owe. */
    private function guardAmount(?Sale $sale, string $type, Money $amount): void
    {
        if (! $sale || in_array($type, [BuyerPayment::TYPE_LATE_FEE, BuyerPayment::TYPE_REFUND], true)) {
            return;
        }

        $outstanding = $sale->isFinalized()
            ? $sale->outstandingReceivable()
            : Money::of($sale->sale_price)->minus($sale->receivedTotal());

        if ($amount->greaterThan($outstanding)) {
            throw new RuntimeException(sprintf(
                'That is more than the %s still outstanding on this sale.',
                $outstanding->format(),
            ));
        }
    }

    /** Reverse a receipt with a mirror journal; the original row stays. */
    public function reverse(BuyerPayment $payment, string $reason): void
    {
        if (! $payment->isConfirmed()) {
            throw new RuntimeException('Only a confirmed receipt can be reversed.');
        }

        DB::transaction(function () use ($payment, $reason) {
            $this->ledger->reverse($payment->journal, $reason);
            $payment->update(['status' => BuyerPayment::STATUS_REVERSED]);

            $payment->installment?->refreshPaidAmount();

            $this->audit->log(
                action: 'buyer_payment.reversed',
                subject: $payment,
                description: Money::of($payment->amount)->format(),
                reason: $reason,
            );
        });
    }

    /**
     * Build or replace the installment plan for a sale.
     *
     * @param  list<array{due_date:string,amount:mixed,notes?:string}>  $rows
     */
    public function setSchedule(Sale $sale, array $rows): void
    {
        DB::transaction(function () use ($sale, $rows) {
            $paid = $sale->installments()->where('paid_amount', '>', 0)->exists();

            if ($paid) {
                throw new RuntimeException('Payments have already been received against this schedule, so it cannot be replaced. Adjust the remaining rows individually.');
            }

            $sale->installments()->delete();

            foreach (array_values($rows) as $index => $row) {
                if (blank($row['due_date'] ?? null) || blank($row['amount'] ?? null)) {
                    continue;
                }

                Installment::create([
                    'sale_id' => $sale->id,
                    'installment_no' => $index + 1,
                    'due_date' => $row['due_date'],
                    'amount' => Money::of($row['amount'])->toDecimal(),
                    'notes' => $row['notes'] ?? null,
                    'status' => Installment::STATUS_PENDING,
                ]);
            }
        });
    }
}
