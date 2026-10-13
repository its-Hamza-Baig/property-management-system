<?php

namespace App\Services\Ledger;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A journal being assembled before it is posted.
 *
 * Built by the business services (purchases, sales, payments, distributions)
 * and handed to Ledger::post(), which refuses anything that does not balance.
 */
class JournalDraft
{
    /** @var list<array{account:string,debit:Money,credit:Money,property_id:?int,investor_id:?int,memo:?string}> */
    private array $lines = [];

    private ?Model $source = null;

    private ?int $reversalOfId = null;

    private bool $isAdjustment = false;

    private ?string $reason = null;

    public function __construct(
        private readonly string $date,
        private readonly string $memo,
    ) {
    }

    public static function make(string $date, string $memo): self
    {
        return new self($date, $memo);
    }

    /**
     * @param  string  $account  a LedgerAccounts system key, or "cash:<financial_account_id>"
     *                           or "expense_category:<expense_category_id>"
     */
    public function debit(string $account, mixed $amount, ?int $propertyId = null, ?int $investorId = null, ?string $memo = null): self
    {
        return $this->line($account, Money::of($amount), Money::zero(), $propertyId, $investorId, $memo);
    }

    public function credit(string $account, mixed $amount, ?int $propertyId = null, ?int $investorId = null, ?string $memo = null): self
    {
        return $this->line($account, Money::zero(), Money::of($amount), $propertyId, $investorId, $memo);
    }

    private function line(string $account, Money $debit, Money $credit, ?int $propertyId, ?int $investorId, ?string $memo): self
    {
        // A negative leg is always expressible as the opposite leg, and keeping
        // every stored amount non-negative makes the reports trivially correct.
        if ($debit->isNegative()) {
            [$debit, $credit] = [Money::zero(), $debit->negated()];
        }

        if ($credit->isNegative()) {
            [$debit, $credit] = [$credit->negated(), Money::zero()];
        }

        if ($debit->isZero() && $credit->isZero()) {
            return $this;
        }

        $this->lines[] = [
            'account' => $account,
            'debit' => $debit,
            'credit' => $credit,
            'property_id' => $propertyId,
            'investor_id' => $investorId,
            'memo' => $memo,
        ];

        return $this;
    }

    public function for(Model $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function reversing(int $journalId, string $reason): self
    {
        $this->reversalOfId = $journalId;
        $this->reason = $reason;

        return $this;
    }

    public function asAdjustment(string $reason): self
    {
        $this->isAdjustment = true;
        $this->reason = $reason;

        return $this;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function memo(): string
    {
        return $this->memo;
    }

    /** @return list<array{account:string,debit:Money,credit:Money,property_id:?int,investor_id:?int,memo:?string}> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function source(): ?Model
    {
        return $this->source;
    }

    public function reversalOfId(): ?int
    {
        return $this->reversalOfId;
    }

    public function isAdjustment(): bool
    {
        return $this->isAdjustment;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function totalDebit(): Money
    {
        return Money::sum(array_column($this->lines, 'debit'));
    }

    public function totalCredit(): Money
    {
        return Money::sum(array_column($this->lines, 'credit'));
    }

    public function balances(): bool
    {
        return $this->totalDebit()->equals($this->totalCredit());
    }
}
