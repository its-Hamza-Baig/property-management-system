<?php

namespace App\Services\Sales;

use App\Models\BuyerPayment;
use App\Models\Expense;
use App\Models\InvestorProfitPayable;
use App\Models\Journal;
use App\Models\Property;
use App\Models\PropertyInvestorAllocation;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Close the sale and work out what was actually made on it.
     *
     *   realised profit = sale price - allocated property cost - sale expenses
     *
     * The cost is frozen onto the sale at this moment so that a later edit to
     * a cost or a category cannot quietly restate a closed deal.
     *
     * Finalising says nothing about cash. A sale can close with the buyer
     * still owing most of the money; the receivable and the receipts stay
     * separate numbers throughout.
     */
    public function finalize(Sale $sale, ?string $finalizedOn = null): Sale
    {
        if ($sale->isFinalized()) {
            throw new RuntimeException('This sale has already been finalised.');
        }

        if ($sale->isCancelled()) {
            throw new RuntimeException('A cancelled sale cannot be finalised. Create a new sale instead.');
        }

        $property = $sale->property;
        $purchase = $property->purchase;

        if (! $purchase || ! $purchase->isConfirmed()) {
            throw new RuntimeException('Confirm the property purchase before finalising a sale.');
        }

        return DB::transaction(function () use ($sale, $property, $finalizedOn) {
            Sale::whereKey($sale->id)->lockForUpdate()->first();

            $date = $finalizedOn ?? $sale->agreement_date->toDateString();
            $salePrice = Money::of($sale->sale_price);
            $allocatedCost = $property->totalCost();
            $saleExpenses = $sale->saleExpenseTotal();
            $profit = $salePrice->minus($allocatedCost)->minus($saleExpenses);

            $draft = JournalDraft::make($date, 'Sale finalised: '.$property->reference)
                ->for($sale)
                // Recognise the sale and what the buyer owes.
                ->debit(LedgerAccounts::BUYER_RECEIVABLE, $salePrice, $property->id)
                ->credit(LedgerAccounts::SALE_REVENUE, $salePrice, $property->id)
                // Release the carrying cost of the property.
                ->debit(LedgerAccounts::COST_OF_PROPERTIES_SOLD, $allocatedCost, $property->id)
                ->credit(LedgerAccounts::PROPERTY_COST, $allocatedCost, $property->id);

            // Anything already taken as a token or advance stops being a
            // liability and settles part of the receivable instead.
            $advance = $this->advanceHeldFor($sale);

            if ($advance->isPositive()) {
                $draft->debit(LedgerAccounts::BUYER_ADVANCE, $advance, $property->id)
                    ->credit(LedgerAccounts::BUYER_RECEIVABLE, $advance, $property->id);
            }

            $this->ledger->post($draft);

            $sale->update([
                'status' => Sale::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
                'allocated_cost' => $allocatedCost->toDecimal(),
                'sale_expense_total' => $saleExpenses->toDecimal(),
                'realized_profit' => $profit->toDecimal(),
            ]);

            $property->update(['status' => Property::STATUS_SOLD]);

            if ($sale->reservation) {
                $sale->reservation->update(['status' => Reservation::STATUS_CONVERTED]);
            }

            $this->createProfitPayables($sale, $profit, $date);

            $this->audit->log(
                action: 'sale.finalized',
                subject: $sale,
                description: sprintf(
                    'Sale %s, cost %s, sale expenses %s, realised profit %s',
                    $salePrice->format(),
                    $allocatedCost->format(),
                    $saleExpenses->format(),
                    $profit->format(),
                ),
            );

            return $sale->refresh();
        });
    }

    /**
     * Split the realised result between the participants using the ratios
     * that were in force, and record what is owed to each of them.
     *
     * The calculated share is recorded as a payable. Nothing is paid here:
     * when and whether to actually distribute is the decision of the account
     * holder, and it is tracked separately.
     */
    private function createProfitPayables(Sale $sale, Money $profit, string $date): Collection
    {
        $allocations = $sale->property->allocations()
            ->inForceOn($date)
            ->get();

        if ($allocations->isEmpty()) {
            // No investors recorded: the whole result belongs to the owner.
            $allocations = collect([new PropertyInvestorAllocation([
                'is_owner' => true,
                'profit_share_percentage' => 100,
            ])]);
        }

        $percentages = [];

        foreach ($allocations as $index => $allocation) {
            $percentages[$index] = (float) $allocation->profit_share_percentage;
        }

        // Largest-remainder split, so the parts add back to the exact total
        // and no paisa is invented or lost.
        $shares = Money::allocateByPercentages($profit, $percentages);

        $draft = JournalDraft::make($date, 'Profit allocation: '.$sale->property->reference)
            ->for($sale)
            ->debit(LedgerAccounts::RETAINED_RESULT, $profit, $sale->property_id);

        $payables = collect();

        foreach ($allocations as $index => $allocation) {
            $share = $shares[$index] ?? Money::zero();

            if ($share->isZero()) {
                continue;
            }

            if ($allocation->is_owner) {
                // The owner share simply stays in owner equity. It is not a
                // payable, because the business does not owe itself money.
                $draft->credit(LedgerAccounts::OWNER_CAPITAL, $share, $sale->property_id);
            } else {
                $draft->credit(
                    LedgerAccounts::INVESTOR_PROFIT_PAYABLE,
                    $share,
                    $sale->property_id,
                    $allocation->investor_id,
                );
            }

            $payables->push(InvestorProfitPayable::create([
                'sale_id' => $sale->id,
                'property_id' => $sale->property_id,
                'investor_id' => $allocation->investor_id,
                'is_owner' => (bool) $allocation->is_owner,
                'profit_share_percentage' => $allocation->profit_share_percentage,
                'profit_amount' => $share->toDecimal(),
                'paid_amount' => '0.00',
                'status' => InvestorProfitPayable::STATUS_OPEN,
            ]));
        }

        if (! $draft->isEmpty()) {
            $this->ledger->post($draft);
        }

        return $payables;
    }

    /**
     * Cancel a sale, keeping the record and everything that happened on it.
     *
     * The sale row is never deleted and a later sale of the same property is
     * a new record, not a rewrite of this one.
     */
    public function cancel(Sale $sale, array $data): Sale
    {
        if ($sale->isCancelled()) {
            throw new RuntimeException('This sale is already cancelled.');
        }

        $reason = $data['cancellation_reason'];
        $forfeited = Money::of($data['forfeited_amount'] ?? 0);
        $date = $data['cancellation_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($sale, $data, $reason, $forfeited, $date) {
            $wasFinalized = $sale->isFinalized();

            if ($wasFinalized) {
                // Undo the finalisation and the profit allocation with mirror
                // journals. The originals stay in the ledger.
                foreach ($this->journalsFor($sale) as $journal) {
                    if (! $journal->isReversed()) {
                        $this->ledger->reverse($journal, 'Sale cancelled: '.$reason, $date);
                    }
                }

                $this->settleProfitPayablesOnCancellation($sale, $date, $reason);

                // Receipts taken after finalisation were credited to the
                // receivable. Reversing finalisation strands them there, so
                // move them back into the advance, which is where the refund
                // and forfeiture rules can actually see them.
                $this->reclassifyReceivableReceipts($sale, $date, $reason);
            }

            // A forfeited token stops being a liability and becomes income.
            if ($forfeited->isPositive()) {
                $available = $this->advanceHeldFor($sale);

                if ($forfeited->greaterThan($available)) {
                    throw new RuntimeException(sprintf(
                        'Only %s was received from the buyer, so %s cannot be forfeited.',
                        $available->format(),
                        $forfeited->format(),
                    ));
                }

                $this->ledger->post(
                    JournalDraft::make($date, 'Forfeiture on cancelled sale: '.$sale->property->reference)
                        ->debit(LedgerAccounts::BUYER_ADVANCE, $forfeited, $sale->property_id)
                        ->credit(LedgerAccounts::FORFEITURE_INCOME, $forfeited, $sale->property_id)
                        ->for($sale)
                );
            }

            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'cancellation_date' => $date,
                'cancellation_reason' => $reason,
                'forfeited_amount' => $forfeited->toDecimal(),
            ]);

            // The property can go back on the shelf once the settlement
            // allows it. Its whole history stays attached to it.
            if (($data['return_to_available'] ?? true) && $sale->property->isSold()) {
                $sale->property->update(['status' => Property::STATUS_AVAILABLE]);
            }

            $this->audit->log(
                action: 'sale.cancelled',
                subject: $sale,
                description: sprintf(
                    'Cancelled. Forfeited %s, outstanding refund %s',
                    $forfeited->format(),
                    $this->refundDueFor($sale->refresh())->format(),
                ),
                reason: $reason,
            );

            return $sale->refresh();
        });
    }

    /**
     * Correct a finalised sale.
     *
     * The original figures are kept in the audit trail, the adjustment is a
     * new journal, and amounts already paid to investors are left untouched:
     * a correction can only change what is still outstanding.
     */
    public function correct(Sale $sale, array $data, string $reason): Sale
    {
        if (! $sale->isFinalized()) {
            throw new RuntimeException('Only a finalised sale is corrected this way. Edit the draft instead.');
        }

        return DB::transaction(function () use ($sale, $data, $reason) {
            $before = [
                'sale_price' => $sale->sale_price,
                'allocated_cost' => $sale->allocated_cost,
                'sale_expense_total' => $sale->sale_expense_total,
                'realized_profit' => $sale->realized_profit,
            ];

            $date = $data['correction_date'] ?? now()->toDateString();
            $oldPrice = Money::of($sale->sale_price);
            $newPrice = Money::of($data['sale_price'] ?? $sale->sale_price);
            $priceDelta = $newPrice->minus($oldPrice);

            // Sale expenses may have been added since finalisation.
            $newExpenses = $sale->saleExpenseTotal();
            $expenseDelta = $newExpenses->minus(Money::of($sale->sale_expense_total));

            $oldProfit = Money::of($sale->realized_profit);
            $newProfit = $newPrice
                ->minus(Money::of($sale->allocated_cost))
                ->minus($newExpenses);
            $profitDelta = $newProfit->minus($oldProfit);

            if ($priceDelta->isZero() && $expenseDelta->isZero()) {
                throw new RuntimeException('Nothing has changed, so there is nothing to correct.');
            }

            if (! $priceDelta->isZero()) {
                $this->ledger->post(
                    JournalDraft::make($date, 'Sale correction: '.$sale->property->reference)
                        ->asAdjustment($reason)
                        ->debit(LedgerAccounts::BUYER_RECEIVABLE, $priceDelta, $sale->property_id)
                        ->credit(LedgerAccounts::SALE_REVENUE, $priceDelta, $sale->property_id)
                        ->for($sale)
                );
            }

            $sale->update([
                'sale_price' => $newPrice->toDecimal(),
                'sale_expense_total' => $newExpenses->toDecimal(),
                'realized_profit' => $newProfit->toDecimal(),
            ]);

            if (! $profitDelta->isZero()) {
                $this->adjustProfitPayables($sale, $profitDelta, $date, $reason);
            }

            $this->audit->logChange('sale.corrected', $sale->refresh(), $before, $reason);

            return $sale->refresh();
        });
    }

    /**
     * Spread a change in profit across the existing payables, adjusting only
     * what is still owed. Amounts already handed over are never rewritten.
     */
    private function adjustProfitPayables(Sale $sale, Money $delta, string $date, string $reason): void
    {
        $payables = $sale->profitPayables()->get();

        if ($payables->isEmpty()) {
            return;
        }

        $percentages = [];

        foreach ($payables as $index => $payable) {
            $percentages[$index] = (float) $payable->profit_share_percentage;
        }

        $shares = Money::allocateByPercentages($delta, $percentages);

        $draft = JournalDraft::make($date, 'Profit correction: '.$sale->property->reference)
            ->asAdjustment($reason)
            ->for($sale)
            ->debit(LedgerAccounts::RETAINED_RESULT, $delta, $sale->property_id);

        foreach ($payables as $index => $payable) {
            $share = $shares[$index] ?? Money::zero();

            if ($share->isZero()) {
                continue;
            }

            if ($payable->is_owner) {
                $draft->credit(LedgerAccounts::OWNER_CAPITAL, $share, $sale->property_id);
            } else {
                $draft->credit(
                    LedgerAccounts::INVESTOR_PROFIT_PAYABLE,
                    $share,
                    $sale->property_id,
                    $payable->investor_id,
                );
            }

            $payable->update([
                'profit_amount' => $payable->profit()->plus($share)->toDecimal(),
            ]);
            $payable->refreshPaidAmount();
        }

        if (! $draft->isEmpty()) {
            $this->ledger->post($draft);
        }
    }

    /**
     * On cancellation the calculated profit goes away, but money already
     * distributed does not. Each payable is written back to what was actually
     * paid; anything overpaid stays visible as a negative balance that has to
     * be recovered, rather than being silently erased.
     */
    private function settleProfitPayablesOnCancellation(Sale $sale, string $date, string $reason): void
    {
        foreach ($sale->profitPayables as $payable) {
            $outstanding = $payable->outstanding();

            if ($outstanding->isZero()) {
                continue;
            }

            $payable->update(['profit_amount' => $payable->paid()->toDecimal()]);
            $payable->refreshPaidAmount();
        }

        $this->audit->log(
            action: 'sale.profit_payables_settled',
            subject: $sale,
            description: 'Unpaid investor profit removed after cancellation. Amounts already paid were left unchanged.',
            reason: $reason,
        );
    }

    /**
     * Buyer money sitting in Buyer Advance for this sale.
     *
     * Read from what each receipt was actually credited to when it was
     * posted, not inferred from its type, because a receipt taken before
     * finalisation lands in the advance whatever it is called.
     */
    public function advanceHeldFor(Sale $sale): Money
    {
        $query = $this->receiptsFor($sale)
            ->where('credited_to', LedgerAccounts::BUYER_ADVANCE);

        $in = Money::of((clone $query)->where('type', '!=', BuyerPayment::TYPE_REFUND)->sum('amount'));
        $out = Money::of((clone $query)->where('type', BuyerPayment::TYPE_REFUND)->sum('amount'));

        return $in->minus($out);
    }

    /**
     * Put money received after finalisation back into Buyer Advance when the
     * sale is cancelled, so it reads as "held and owed back" rather than as a
     * negative receivable.
     */
    private function reclassifyReceivableReceipts(Sale $sale, string $date, string $reason): void
    {
        $amount = $this->receivableReceiptsFor($sale);

        if (! $amount->isPositive()) {
            return;
        }

        $this->ledger->post(
            JournalDraft::make($date, 'Receipts returned to buyer advance on cancellation: '.$sale->property->reference)
                ->asAdjustment($reason)
                ->debit(LedgerAccounts::BUYER_RECEIVABLE, $amount, $sale->property_id)
                ->credit(LedgerAccounts::BUYER_ADVANCE, $amount, $sale->property_id)
                ->for($sale)
        );

        $this->receiptsFor($sale)
            ->where('credited_to', LedgerAccounts::BUYER_RECEIVABLE)
            ->update(['credited_to' => LedgerAccounts::BUYER_ADVANCE]);
    }

    /** Receipts credited straight to the receivable, i.e. taken after finalisation. */
    private function receivableReceiptsFor(Sale $sale): Money
    {
        return Money::of(
            $this->receiptsFor($sale)
                ->where('credited_to', LedgerAccounts::BUYER_RECEIVABLE)
                ->sum('amount')
        );
    }

    private function receiptsFor(Sale $sale)
    {
        return BuyerPayment::where('status', BuyerPayment::STATUS_CONFIRMED)
            ->where(function ($q) use ($sale) {
                $q->where('sale_id', $sale->id);

                if ($sale->reservation_id) {
                    $q->orWhere('reservation_id', $sale->reservation_id);
                }
            });
    }

    public function refundDueFor(Sale $sale): Money
    {
        return $this->advanceHeldFor($sale)->minus(Money::of($sale->forfeited_amount));
    }

    /** @return Collection<int, Journal> */
    private function journalsFor(Sale $sale): Collection
    {
        return Journal::where('source_type', $sale->getMorphClass())
            ->where('source_id', $sale->getKey())
            ->whereNull('reversal_of_id')
            ->orderBy('id')
            ->get();
    }

    /** Sale expenses are ordinary expenses tagged with the sale. */
    public function saleExpenses(Sale $sale): Collection
    {
        return $sale->expenses()->with('category')->where('status', Expense::STATUS_CONFIRMED)->get();
    }
}
