<?php

use App\Support\Money;

if (! function_exists('pkr')) {
    /**
     * Format an amount for display, e.g. pkr('1250000') => "PKR 1,250,000.00".
     *
     * Views never do arithmetic; they only format what a service calculated.
     */
    function pkr(mixed $amount, bool $withSymbol = true): string
    {
        return Money::of($amount)->format($withSymbol);
    }
}

if (! function_exists('money_class')) {
    /** Red for negative amounts, so a loss is visible at a glance. */
    function money_class(mixed $amount): string
    {
        return Money::of($amount)->isNegative() ? 'money text-money-negative' : 'money';
    }
}

if (! function_exists('pms_date')) {
    /** Human readable date, or a dash when there is nothing to show. */
    function pms_date(mixed $date, string $format = 'd M Y'): string
    {
        if (blank($date)) {
            return '-';
        }

        return $date instanceof DateTimeInterface
            ? $date->format($format)
            : \Illuminate\Support\Carbon::parse($date)->format($format);
    }
}
