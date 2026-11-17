<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * All money in the app is an integer number of nano-dollars (1e-9 USD).
 *
 * Pixelforge prices are fractions of a cent and its usage report has nine
 * decimals, so nano-dollars let us store every cost exactly, add them up in
 * SQL without floats, and still match Pixelforge to the last digit.
 */
final class Money
{
    public const NANOS_PER_USD = 1_000_000_000;

    /** "0.0125" → 12500000 */
    public static function toNanos(string|int $usd): int
    {
        return BigDecimal::of($usd)
            ->multipliedBy(self::NANOS_PER_USD)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }

    /** 12500000 → "0.012500000" */
    public static function toUsd(int $nanos): string
    {
        return (string) BigDecimal::ofUnscaledValue($nanos, 9);
    }
}
