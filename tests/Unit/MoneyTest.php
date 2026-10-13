<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Money is the foundation every financial figure rests on, so it is tested
 * directly rather than only through the business services.
 */
class MoneyTest extends TestCase
{
    public function test_it_parses_the_shapes_amounts_actually_arrive_in(): void
    {
        // Decimal strings out of the database.
        $this->assertSame('1250000.00', Money::of('1250000.00')->toDecimal());
        // Whole numbers typed into a form.
        $this->assertSame('5000.00', Money::of('5000')->toDecimal());
        // Integers from a calculation.
        $this->assertSame('42.00', Money::of(42)->toDecimal());
        // A blank field is zero, not an error.
        $this->assertSame('0.00', Money::of('')->toDecimal());
        $this->assertSame('0.00', Money::of(null)->toDecimal());
        // Negatives, for losses and reversals.
        $this->assertSame('-750.25', Money::of('-750.25')->toDecimal());
    }

    public function test_it_refuses_something_that_is_not_an_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('1,250,000');
    }

    public function test_it_rounds_half_up_at_the_third_decimal(): void
    {
        $this->assertSame('10.01', Money::of('10.005')->toDecimal());
        $this->assertSame('10.00', Money::of('10.004')->toDecimal());
        $this->assertSame('-10.01', Money::of('-10.005')->toDecimal());
    }

    public function test_addition_is_exact_where_floats_are_not(): void
    {
        // 0.1 + 0.2 === 0.3 is false for floats. It must be true here.
        $sum = Money::of('0.10')->plus('0.20');

        $this->assertSame('0.30', $sum->toDecimal());
        $this->assertTrue($sum->equals('0.30'));

        // A long run of additions must not drift.
        $total = Money::zero();

        for ($i = 0; $i < 1000; $i++) {
            $total = $total->plus('0.01');
        }

        $this->assertSame('10.00', $total->toDecimal());
    }

    public function test_subtraction_and_comparison(): void
    {
        $balance = Money::of('1000000')->minus('250000');

        $this->assertSame('750000.00', $balance->toDecimal());
        $this->assertTrue($balance->greaterThan('700000'));
        $this->assertTrue($balance->lessThan('800000'));
        $this->assertTrue($balance->greaterThanOrEqual('750000'));
        $this->assertFalse($balance->isNegative());
        $this->assertTrue(Money::of('-1')->isNegative());
        $this->assertTrue(Money::zero()->isZero());
    }

    public function test_percentages_round_to_the_nearest_paisa(): void
    {
        $this->assertSame('250000.00', Money::of('1000000')->percentage(25)->toDecimal());
        $this->assertSame('333333.00', Money::of('1000000')->percentage('33.3333')->toDecimal());
        $this->assertSame('0.33', Money::of('1')->percentage('33.3333')->toDecimal());
    }

    public function test_summing_a_collection_of_mixed_inputs(): void
    {
        $total = Money::sum(['100.50', 200, Money::of('0.50'), null]);

        $this->assertSame('301.00', $total->toDecimal());
        $this->assertSame('0.00', Money::sum([])->toDecimal());
    }

    /**
     * The rule that matters most when profit is divided: the parts must add
     * back to exactly the whole, with no paisa invented or lost.
     */
    public function test_allocation_always_adds_back_to_the_original(): void
    {
        $cases = [
            ['100001.00', ['a' => 33.3333, 'b' => 33.3333, 'c' => 33.3334]],
            ['0.01', ['a' => 50, 'b' => 50]],
            ['999999.99', ['a' => 70, 'b' => 30]],
            ['1000000.00', ['a' => 16.6667, 'b' => 16.6667, 'c' => 16.6666, 'd' => 50]],
            ['-50000.00', ['a' => 60, 'b' => 40]],
        ];

        foreach ($cases as [$amount, $percentages]) {
            $parts = Money::allocateByPercentages(Money::of($amount), $percentages);
            $total = Money::sum($parts);

            $this->assertSame(
                $amount,
                $total->toDecimal(),
                "Allocating {$amount} across ".count($percentages).' shares did not add back exactly.',
            );
        }
    }

    public function test_allocating_a_loss_gives_every_participant_a_negative_share(): void
    {
        $parts = Money::allocateByPercentages(Money::of('-30000'), ['a' => 50, 'b' => 50]);

        $this->assertSame('-15000.00', $parts['a']->toDecimal());
        $this->assertSame('-15000.00', $parts['b']->toDecimal());
    }

    public function test_allocating_nothing_returns_nothing(): void
    {
        $this->assertSame([], Money::allocateByPercentages(Money::of('100'), []));
    }

    public function test_it_formats_for_a_human(): void
    {
        $this->assertSame('PKR 1,250,000.00', Money::of('1250000')->format());
        $this->assertSame('1,250,000.00', Money::of('1250000')->format(false));
        $this->assertSame('PKR -500.00', Money::of('-500')->format());
    }

    public function test_paisa_round_trip_survives_the_database(): void
    {
        // What goes into DECIMAL(18,2) must come back identical.
        foreach (['0.01', '0.99', '1.00', '999999999.99', '-0.01'] as $value) {
            $this->assertSame($value, Money::of(Money::of($value)->toDecimal())->toDecimal());
        }
    }
}
