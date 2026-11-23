<?php

namespace App\Support;

/**
 * Money is always handled as an integer number of paisa. Suppliers hand us
 * decimal rupee strings (e.g. "84250.50") which we parse without ever
 * touching floating point, to avoid rounding drift on real money.
 */
final class Money
{
    public static function fromSupplierString(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new \InvalidArgumentException("Invalid supplier amount format: \"{$amount}\"");
        }

        $rupees = (int) $matches[1];
        $paisaFraction = str_pad($matches[2] ?? '0', 2, '0', STR_PAD_RIGHT);

        return $rupees * 100 + (int) $paisaFraction;
    }

    public static function toRupeeString(int $paisa): string
    {
        $negative = $paisa < 0;
        $paisa = abs($paisa);

        $rupees = intdiv($paisa, 100);
        $fraction = $paisa % 100;

        return ($negative ? '-' : '').$rupees.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function percentageOf(int $paisa, float $percent): int
    {
        return (int) round($paisa * $percent / 100);
    }
}
