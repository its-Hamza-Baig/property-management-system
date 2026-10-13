<?php

namespace App\Services\Investors;

use App\Models\FinancialAccount;
use App\Models\Investor;
use App\Models\InvestorShareTransfer;
use App\Models\Property;
use App\Models\PropertyInvestorAllocation;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AllocationService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Replace the allocation table for a property.
     *
     * Existing rows are closed off with an effective_to date rather than
     * edited, so the ratios that applied to any past event stay readable.
     *
     * @param  list<array{investor_id:int,capital_amount:mixed,ownership_percentage?:mixed,profit_share_percentage?:mixed,contribution_date?:string}>  $rows
     */
    public function allocate(Property $property, array $rows, string $effectiveFrom, ?string $reason = null): Collection
    {
        if ($property->isSold()) {
            throw new RuntimeException('The investor allocation of a sold property cannot be changed. Use an audited correction instead.');
        }

        $rows = array_values(array_filter($rows, fn ($row) => ! empty($row['investor_id'])));

        $investorCapital = Money::sum(array_map(
            fn ($row) => Money::of($row['capital_amount'] ?? 0),
            $rows,
        ));

        $costBase = $property->totalCost();

        // The cap: investor capital can never exceed what the property
        // actually cost. Whatever is left over is the account holder stake.
        if ($investorCapital->greaterThan($costBase)) {
            throw new RuntimeException(sprintf(
                'Investor capital of %s is more than the total property cost of %s (purchase price plus capitalised purchase expenses). Reduce the allocations or record the additional cost first.',
                $investorCapital->format(),
                $costBase->format(),
            ));
        }

        $ownerCapital = $costBase->minus($investorCapital);

        $percentages = $this->resolvePercentages($rows, $investorCapital, $ownerCapital, $costBase);

        return DB::transaction(function () use ($property, $rows, $effectiveFrom, $reason, $ownerCapital, $percentages) {
            $previous = $property->allocations()->active()->get();

            // Close, do not overwrite.
            foreach ($previous as $allocation) {
                $allocation->update([
                    'effective_to' => $effectiveFrom,
                    'status' => PropertyInvestorAllocation::STATUS_TRANSFERRED,
                ]);
            }

            $created = collect();

            foreach ($rows as $index => $row) {
                $created->push(PropertyInvestorAllocation::create([
                    'property_id' => $property->id,
                    'investor_id' => $row['investor_id'],
                    'is_owner' => false,
                    'capital_amount' => Money::of($row['capital_amount'] ?? 0)->toDecimal(),
                    'contribution_date' => $row['contribution_date'] ?? $effectiveFrom,
                    'ownership_percentage' => $percentages['ownership'][$index],
                    'profit_share_percentage' => $percentages['profit'][$index],
                    'status' => PropertyInvestorAllocation::STATUS_ACTIVE,
                    'effective_from' => $effectiveFrom,
                    'agreement_notes' => $row['agreement_notes'] ?? null,
                    'created_by' => auth()->id(),
                ]));
            }

            // The account holder is always a participant row, so the
            // percentages add to exactly 100 without special cases elsewhere.
            if ($ownerCapital->isPositive() || $created->isEmpty()) {
                $created->push(PropertyInvestorAllocation::create([
                    'property_id' => $property->id,
                    'investor_id' => null,
                    'is_owner' => true,
                    'capital_amount' => $ownerCapital->toDecimal(),
                    'contribution_date' => $effectiveFrom,
                    'ownership_percentage' => $percentages['owner_ownership'],
                    'profit_share_percentage' => $percentages['owner_profit'],
                    'status' => PropertyInvestorAllocation::STATUS_ACTIVE,
                    'effective_from' => $effectiveFrom,
                    'created_by' => auth()->id(),
                ]));
            }

            $this->assertPercentagesTotal($property);

            $this->audit->log(
                action: 'allocation.updated',
                subject: $property,
                description: 'Investor allocations updated for '.$property->reference,
                oldValues: $previous->map(fn ($a) => [
                    'investor_id' => $a->investor_id,
                    'capital' => $a->capital_amount,
                    'ownership' => $a->ownership_percentage,
                    'profit' => $a->profit_share_percentage,
                ])->all(),
                newValues: $created->map(fn ($a) => [
                    'investor_id' => $a->investor_id,
                    'capital' => $a->capital_amount,
                    'ownership' => $a->ownership_percentage,
                    'profit' => $a->profit_share_percentage,
                ])->all(),
                reason: $reason,
            );

            return $created;
        });
    }

    /**
     * Record cash actually received from an investor as capital.
     *
     * Separate from the allocation itself, because an allocation is an
     * agreement and this is a receipt. Posting it credits investor capital,
     * never income.
     */
    public function recordCapitalReceipt(Investor $investor, array $data): void
    {
        $amount = Money::of($data['amount']);

        if (! $amount->isPositive()) {
            throw new RuntimeException('The capital amount must be greater than zero.');
        }

        $account = FinancialAccount::findOrFail($data['financial_account_id']);

        DB::transaction(function () use ($investor, $data, $amount, $account) {
            $this->ledger->post(
                JournalDraft::make(
                    $data['received_date'],
                    'Investor capital received: '.$investor->name,
                )
                    ->debit('cash:'.$account->id, $amount, $data['property_id'] ?? null, $investor->id)
                    ->credit(LedgerAccounts::INVESTOR_CAPITAL, $amount, $data['property_id'] ?? null, $investor->id)
                    ->for($investor)
            );

            $this->audit->log(
                action: 'investor.capital_received',
                subject: $investor,
                description: $amount->format().' received into '.$account->name,
            );
        });
    }

    /**
     * Move part or all of one holding to another investor, or to and from the
     * account holder.
     *
     * This is a settlement between two parties. It is deliberately kept away
     * from sale revenue and realised profit: transferring a share is not a
     * property sale.
     */
    public function transferShare(Property $property, array $data): InvestorShareTransfer
    {
        if ($property->isSold()) {
            throw new RuntimeException('Shares cannot be transferred after the property has been sold.');
        }

        return DB::transaction(function () use ($property, $data) {
            $fromId = $data['from_investor_id'] ?? null;
            $toId = $data['to_investor_id'] ?? null;

            if ($fromId === null && $toId === null) {
                throw new RuntimeException('Choose who the share is moving from and to.');
            }

            if ($fromId !== null && (int) $fromId === (int) $toId) {
                throw new RuntimeException('The two sides of a transfer must be different.');
            }

            $source = $property->allocations()->active()
                ->when($fromId, fn ($q) => $q->where('investor_id', $fromId))
                ->when($fromId === null, fn ($q) => $q->where('is_owner', true))
                ->first();

            if (! $source) {
                throw new RuntimeException('That party does not currently hold a share in this property.');
            }

            $ownershipMoved = (float) $data['ownership_percentage'];
            $profitMoved = (float) ($data['profit_share_percentage'] ?? $ownershipMoved);
            $capitalMoved = Money::of($data['capital_amount'] ?? 0);

            if ($ownershipMoved <= 0) {
                throw new RuntimeException('Enter the ownership percentage being transferred.');
            }

            if ($ownershipMoved > (float) $source->ownership_percentage + 0.0001) {
                throw new RuntimeException(sprintf(
                    'That party only holds %s percent of this property.',
                    rtrim(rtrim(number_format((float) $source->ownership_percentage, 4, '.', ''), '0'), '.'),
                ));
            }

            $transferDate = $data['transfer_date'];

            $this->adjustHolding($property, $source, -$ownershipMoved, -$profitMoved, $capitalMoved->negated(), $transferDate);

            $target = $property->allocations()->active()
                ->when($toId, fn ($q) => $q->where('investor_id', $toId))
                ->when($toId === null, fn ($q) => $q->where('is_owner', true))
                ->first();

            if ($target) {
                $this->adjustHolding($property, $target, $ownershipMoved, $profitMoved, $capitalMoved, $transferDate);
            } else {
                PropertyInvestorAllocation::create([
                    'property_id' => $property->id,
                    'investor_id' => $toId,
                    'is_owner' => $toId === null,
                    'capital_amount' => $capitalMoved->toDecimal(),
                    'contribution_date' => $transferDate,
                    'ownership_percentage' => $ownershipMoved,
                    'profit_share_percentage' => $profitMoved,
                    'status' => PropertyInvestorAllocation::STATUS_ACTIVE,
                    'effective_from' => $transferDate,
                    'created_by' => auth()->id(),
                ]);
            }

            $transfer = InvestorShareTransfer::create([
                'property_id' => $property->id,
                'from_investor_id' => $fromId,
                'to_investor_id' => $toId,
                'transfer_date' => $transferDate,
                'capital_amount' => $capitalMoved->toDecimal(),
                'ownership_percentage' => $ownershipMoved,
                'profit_share_percentage' => $profitMoved,
                'settlement_amount' => Money::of($data['settlement_amount'] ?? 0)->toDecimal(),
                'settlement_status' => $data['settlement_status'] ?? InvestorShareTransfer::SETTLEMENT_UNPAID,
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Only the capital classification moves on the books. If money
            // changed hands it is posted against the two capital accounts,
            // never against revenue.
            $settlement = Money::of($transfer->settlement_amount);

            if ($settlement->isPositive() && ! empty($data['financial_account_id'])) {
                $journal = $this->ledger->post(
                    JournalDraft::make(
                        $transferDate,
                        'Investor share transfer settlement: '.$property->reference,
                    )
                        ->debit(
                            $fromId ? LedgerAccounts::INVESTOR_CAPITAL : LedgerAccounts::OWNER_CAPITAL,
                            $settlement,
                            $property->id,
                            $fromId,
                        )
                        ->credit('cash:'.$data['financial_account_id'], $settlement, $property->id, $fromId)
                        ->for($transfer)
                );

                $transfer->update(['journal_id' => $journal->id]);
            }

            $this->assertPercentagesTotal($property);

            $this->audit->log(
                action: 'allocation.share_transferred',
                subject: $transfer,
                description: sprintf(
                    '%s percent of %s moved from %s to %s',
                    $ownershipMoved,
                    $property->reference,
                    $transfer->fromName(),
                    $transfer->toName(),
                ),
                reason: $data['reason'] ?? null,
            );

            return $transfer;
        });
    }

    /** Close the old row and open a new one, so history is never rewritten. */
    private function adjustHolding(
        Property $property,
        PropertyInvestorAllocation $holding,
        float $ownershipDelta,
        float $profitDelta,
        Money $capitalDelta,
        string $effectiveFrom,
    ): void {
        $newOwnership = round((float) $holding->ownership_percentage + $ownershipDelta, 4);
        $newProfit = round((float) $holding->profit_share_percentage + $profitDelta, 4);
        $newCapital = $holding->capital()->plus($capitalDelta);

        $holding->update([
            'effective_to' => $effectiveFrom,
            'status' => PropertyInvestorAllocation::STATUS_TRANSFERRED,
        ]);

        if ($newOwnership <= 0.0001 && $newProfit <= 0.0001) {
            return; // Fully exited.
        }

        PropertyInvestorAllocation::create([
            'property_id' => $property->id,
            'investor_id' => $holding->investor_id,
            'is_owner' => $holding->is_owner,
            'capital_amount' => $newCapital->toDecimal(),
            'contribution_date' => $holding->contribution_date,
            'ownership_percentage' => max(0, $newOwnership),
            'profit_share_percentage' => max(0, $newProfit),
            'status' => PropertyInvestorAllocation::STATUS_ACTIVE,
            'effective_from' => $effectiveFrom,
            'agreement_notes' => $holding->agreement_notes,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Ownership and profit sharing are validated independently, because the
     * agreement may legitimately set them to different numbers.
     */
    public function assertPercentagesTotal(Property $property): void
    {
        $active = $property->allocations()->active()->get();

        if ($active->isEmpty()) {
            return;
        }

        $ownership = round($active->sum(fn ($a) => (float) $a->ownership_percentage), 4);
        $profit = round($active->sum(fn ($a) => (float) $a->profit_share_percentage), 4);

        if (abs($ownership - 100) > 0.01) {
            throw new RuntimeException(sprintf('Ownership percentages add up to %s, they must total 100.', $ownership));
        }

        if (abs($profit - 100) > 0.01) {
            throw new RuntimeException(sprintf('Profit sharing percentages add up to %s, they must total 100.', $profit));
        }
    }

    /**
     * Percentages default to the capital ratio but may be set explicitly.
     *
     * @return array{ownership:list<float>,profit:list<float>,owner_ownership:float,owner_profit:float}
     */
    private function resolvePercentages(array $rows, Money $investorCapital, Money $ownerCapital, Money $costBase): array
    {
        $ownership = [];
        $profit = [];
        $ownershipGiven = 0.0;
        $profitGiven = 0.0;

        foreach ($rows as $index => $row) {
            $capital = Money::of($row['capital_amount'] ?? 0);

            $defaultShare = $costBase->isZero()
                ? 0.0
                : round($capital->paisa() / max(1, $costBase->paisa()) * 100, 4);

            $ownership[$index] = isset($row['ownership_percentage']) && $row['ownership_percentage'] !== null && $row['ownership_percentage'] !== ''
                ? round((float) $row['ownership_percentage'], 4)
                : $defaultShare;

            $profit[$index] = isset($row['profit_share_percentage']) && $row['profit_share_percentage'] !== null && $row['profit_share_percentage'] !== ''
                ? round((float) $row['profit_share_percentage'], 4)
                : $ownership[$index];

            $ownershipGiven += $ownership[$index];
            $profitGiven += $profit[$index];
        }

        // Whatever is not allocated to investors belongs to the account holder.
        $ownerOwnership = round(100 - $ownershipGiven, 4);
        $ownerProfit = round(100 - $profitGiven, 4);

        if ($ownerOwnership < -0.01) {
            throw new RuntimeException(sprintf('Investor ownership percentages add up to %s, which is more than 100.', $ownershipGiven));
        }

        if ($ownerProfit < -0.01) {
            throw new RuntimeException(sprintf('Investor profit percentages add up to %s, which is more than 100.', $profitGiven));
        }

        return [
            'ownership' => $ownership,
            'profit' => $profit,
            'owner_ownership' => max(0, $ownerOwnership),
            'owner_profit' => max(0, $ownerProfit),
        ];
    }
}
