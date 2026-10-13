<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic for PKR.
 *
 * Amounts are held as an integer number of paisa, so addition, subtraction and
 * comparison are exact and no float ever touches a financial calculation. The
 * database columns are DECIMAL(18,2); this class is the only place that
 * converts between the two, and the only place that rounds.
 */
final class Money
{
    private function __construct(private readonly int $paisa)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Accepts a decimal string, int, float or another Money. */
    public static function of(mixed $amount): self
    {
        if ($amount instanceof self) {
            return $amount;
        }

        if ($amount === null || $amount === '') {
            return self::zero();
        }

        if (is_int($amount)) {
            return new self($amount * 100);
        }

        $value = is_float($amount) ? sprintf('%.2F', $amount) : trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Not a valid money amount: {$value}");
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        // Round half up on the third decimal rather than truncating.
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');
        $paisa = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $paisa++;
        }

        return new self($negative ? -$paisa : $paisa);
    }

    public static function fromPaisa(int $paisa): self
    {
        return new self($paisa);
    }

    public function paisa(): int
    {
        return $this->paisa;
    }

    public function plus(mixed $other): self
    {
        return new self($this->paisa + self::of($other)->paisa);
    }

    public function minus(mixed $other): self
    {
        return new self($this->paisa - self::of($other)->paisa);
    }

    public function negated(): self
    {
        return new self(-$this->paisa);
    }

    public function abs(): self
    {
        return new self(abs($this->paisa));
    }

    /** Multiply by a percentage, rounding half up to the nearest paisa. */
    public function percentage(string|float $percent): self
    {
        $scaled = (int) round($this->paisa * (float) $percent * 100);

        return new self((int) round($scaled / 10000));
    }

    public function isZero(): bool
    {
        return $this->paisa === 0;
    }

    public function isNegative(): bool
    {
        return $this->paisa < 0;
    }

    public function isPositive(): bool
    {
        return $this->paisa > 0;
    }

    public function equals(mixed $other): bool
    {
        return $this->paisa === self::of($other)->paisa;
    }

    public function greaterThan(mixed $other): bool
    {
        return $this->paisa > self::of($other)->paisa;
    }

    public function greaterThanOrEqual(mixed $other): bool
    {
        return $this->paisa >= self::of($other)->paisa;
    }

    public function lessThan(mixed $other): bool
    {
        return $this->paisa < self::of($other)->paisa;
    }

    /** @param iterable<mixed> $amounts */
    public static function sum(iterable $amounts): self
    {
        $total = self::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public static function min(mixed $a, mixed $b): self
    {
        $a = self::of($a);
        $b = self::of($b);

        return $a->lessThan($b) ? $a : $b;
    }

    public static function max(mixed $a, mixed $b): self
    {
        $a = self::of($a);
        $b = self::of($b);

        return $a->greaterThan($b) ? $a : $b;
    }

    /**
     * Split an amount across percentage shares so that the parts add back up
     * to exactly the original amount. Rounding remainders are handed to the
     * largest shares first, so no paisa is created or lost when profit is
     * distributed between investors.
     *
     * @param  array<int|string, string|float>  $percentages  keyed by participant
     * @return array<int|string, self>
     */
    public static function allocateByPercentages(self $amount, array $percentages): array
    {
        if ($percentages === []) {
            return [];
        }

        $allocated = [];
        $runningTotal = 0;

        foreach ($percentages as $key => $percent) {
            $share = $amount->percentage($percent)->paisa;
            $allocated[$key] = $share;
            $runningTotal += $share;
        }

        $remainder = $amount->paisa - $runningTotal;

        if ($remainder !== 0) {
            // Hand the stray paisa to the biggest shares, one at a time.
            $order = $percentages;
            arsort($order);
            $step = $remainder > 0 ? 1 : -1;

            foreach (array_keys($order) as $key) {
                if ($remainder === 0) {
                    break;
                }

                $allocated[$key] += $step;
                $remainder -= $step;
            }
        }

        return array_map(static fn (int $paisa) => new self($paisa), $allocated);
    }

    /** Decimal string suitable for a DECIMAL(18,2) column. */
    public function toDecimal(): string
    {
        $sign = $this->paisa < 0 ? '-' : '';
        $abs = abs($this->paisa);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    /** Human readable, e.g. "PKR 1,250,000.00". */
    public function format(bool $withSymbol = true): string
    {
        $formatted = number_format($this->paisa / 100, 2, '.', ',');

        return $withSymbol ? 'PKR '.$formatted : $formatted;
    }
}
