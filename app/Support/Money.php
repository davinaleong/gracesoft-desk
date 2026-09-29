<?php

namespace App\Support;

/**
 * Integer-cent arithmetic for money stored as decimal(…, 2).
 *
 * All rounding is half-up on non-negative values, so a sum of rounded
 * lines always equals the stored total.
 */
class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * GST on an amount at a percentage rate (e.g. 9 or 8.25), rounded half-up to the cent.
     */
    public static function percentOf(int $cents, string|int|float $ratePercent): int
    {
        $basisPoints = (int) round(((float) $ratePercent) * 100);

        return self::divideHalfUp($cents * $basisPoints, 10000);
    }

    /**
     * Quantity (2 dp) multiplied by a unit price in cents, rounded half-up to the cent.
     */
    public static function multiply(string|int|float $quantity, int $unitPriceCents): int
    {
        $quantityHundredths = (int) round(((float) $quantity) * 100);

        return self::divideHalfUp($quantityHundredths * $unitPriceCents, 100);
    }

    private static function divideHalfUp(int $numerator, int $denominator): int
    {
        if ($numerator < 0) {
            return -self::divideHalfUp(-$numerator, $denominator);
        }

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
