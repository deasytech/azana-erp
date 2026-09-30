<?php

namespace App\Support;

/** Exact percentage / average maths on decimal strings (no floats). */
final class Ratio
{
    /** part / whole x 100 to $scale decimals, or null when whole is 0. */
    public static function percent(int|string $part, int|string $whole, int $scale = 2): ?string
    {
        if (bccomp((string) $whole, '0', 6) === 0) {
            return null;
        }

        return self::round(bcmul(bcdiv((string) $part, (string) $whole, $scale + 4), '100', $scale + 4), $scale);
    }

    /** total / count to $scale decimals, or null when count is 0 or total is unknown. */
    public static function average(int|string|null $total, int|string $count, int $scale = 2): ?string
    {
        if ($total === null || bccomp((string) $count, '0', 6) === 0) {
            return null;
        }

        return self::round(bcdiv((string) $total, (string) $count, $scale + 4), $scale);
    }

    /** Half-up rounding of a decimal string. */
    private static function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, bccomp($value, '0', $scale + 4) >= 0 ? $half : '-'.$half, $scale);
    }
}
