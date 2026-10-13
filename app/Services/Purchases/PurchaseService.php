<?php

namespace App\Services\Purchases;

use App\Models\FinancialAccount;
use App\Models\PropertyPurchase;
use App\Models\SellerPayment;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Recognise the purchase.
     *
     * This is the one and only moment the purchase price becomes cost. It
     * creates a payable to the sellers; every later payment settles that
     * payable and adds nothing to cost.
     */
    public function confirm(PropertyPurchase $purchase): PropertyPurchase
    {
        if ($purchase->isConfirmed()) {
            throw new RuntimeException('This purchase has already been confirmed.');
        }

        $price = Money::of($purchase->purchase_price);

        if (! $price->isPositive()) {
            throw new RuntimeException('The purchase price must be greater than zero.');
        }

        $this->assertSellerSharesMatchPrice($purchase, $price);

        return DB::transaction(function () use ($purchase, $price) {
            $journal = $this->ledger->post(
                JournalDraft::make(
                    $purchase->purchase_date->toDateString(),
                    'Property purchase: '.$purchase->property->reference,
                )
                    ->debit(LedgerAccounts::PROPERTY_COST, $price, $purchase->property_id)
                    ->credit(LedgerAccounts::SELLER_PAYABLE, $price, $purchase->property_id)
                    ->for($purchase)
            );

            $purchase->update([
                'status' => PropertyPurchase::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);

            $this->audit->log(
                action: 'purchase.confirmed',
                subject: $purchase,
                description: 'Purchase recognised at '.$price->format(),
                newValues: ['journal_id' => $journal->id],
            );

            return $purchase->refresh();
        });
    }

    /**
     * Pay a seller. Reduces the outstanding payable and the chosen cash or
     * bank account. Never touches the property cost.
     */
    public function recordPayment(PropertyPurchase $purchase, array $data): SellerPayment
    {
        if (! $purchase->isConfirmed()) {
            throw new RuntimeException('Confirm the purchase before recording payments to the seller.');
        }

        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($purchase, $data, $amount) {
            // Lock the purchase row so two simultaneous submissions cannot
            // both pass the outstanding-balance check.
            PropertyPurchase::whereKey($purchase->id)->lockForUpdate()->first();

            $outstanding = $purchase->fresh()->outstandingPayable();

            if ($amount->greaterThan($outstanding)) {
                throw new RuntimeException(sprintf(
                    'That payment is more than the outstanding balance of %s.',
                    $outstanding->format(),
                ));
            }

            if (! empty($data['seller_id'])) {
                $sellerOutstanding = $purchase->outstandingForSeller((int) $data['seller_id']);

                if ($amount->greaterThan($sellerOutstanding)) {
                    throw new RuntimeException(sprintf(
                        'That payment is more than the %s still owed to this seller.',
                        $sellerOutstanding->format(),
                    ));
                }
            }

            $account = FinancialAccount::findOrFail($data['financial_account_id']);

            $payment = SellerPayment::create([
                'property_purchase_id' => $purchase->id,
                'seller_id' => $data['seller_id'] ?? null,
                'financial_account_id' => $account->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount->toDecimal(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => SellerPayment::STATUS_CONFIRMED,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $journal = $this->ledger->post(
                JournalDraft::make(
                    $payment->payment_date->toDateString(),
                    'Seller payment: '.$purchase->property->reference,
                )
                    ->debit(LedgerAccounts::SELLER_PAYABLE, $amount, $purchase->property_id)
                    ->credit('cash:'.$account->id, $amount, $purchase->property_id)
                    ->for($payment)
            );

            $payment->update(['journal_id' => $journal->id]);

            return $payment;
        });
    }

    /**
     * Undo a confirmed payment by posting its mirror image. The original row
     * and its journal stay exactly where they are.
     */
    public function reversePayment(SellerPayment $payment, string $reason): SellerPayment
    {
        if (! $payment->isConfirmed()) {
            throw new RuntimeException('Only a confirmed payment can be reversed.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            $journal = $this->ledger->reverse($payment->journal, $reason);

            $payment->update(['status' => SellerPayment::STATUS_REVERSED]);

            $reversal = SellerPayment::create([
                'property_purchase_id' => $payment->property_purchase_id,
                'seller_id' => $payment->seller_id,
                'financial_account_id' => $payment->financial_account_id,
                'payment_date' => now()->toDateString(),
                'amount' => Money::of($payment->amount)->negated()->toDecimal(),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'notes' => 'Reversal: '.$reason,
                'status' => SellerPayment::STATUS_REVERSED,
                'reversal_of_id' => $payment->id,
                'journal_id' => $journal->id,
                'created_by' => auth()->id(),
            ]);

            $this->audit->log(
                action: 'seller_payment.reversed',
                subject: $payment,
                description: 'Reversed '.Money::of($payment->amount)->format(),
                reason: $reason,
            );

            return $reversal;
        });
    }

    /**
     * When sellers are listed individually their shares have to add up to the
     * purchase price, otherwise seller-wise payable tracking would not tally
     * with the total payable.
     */
    private function assertSellerSharesMatchPrice(PropertyPurchase $purchase, Money $price): void
    {
        $shares = $purchase->sellerShares;

        if ($shares->isEmpty()) {
            return;
        }

        $total = Money::sum($shares->pluck('amount'));

        if (! $total->equals($price)) {
            throw new RuntimeException(sprintf(
                'The seller amounts add up to %s but the purchase price is %s.',
                $total->format(),
                $price->format(),
            ));
        }
    }
}
