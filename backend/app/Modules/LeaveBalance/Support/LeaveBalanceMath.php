<?php

namespace App\Modules\LeaveBalance\Support;

class LeaveBalanceMath
{
    private const SCALE = 2;

    public static function add(string|int|float $a, string|int|float $b): string
    {
        return bcadd((string) $a, (string) $b, self::SCALE);
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return bcsub((string) $a, (string) $b, self::SCALE);
    }

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return bcmul((string) $a, (string) $b, self::SCALE);
    }

    public static function div(string|int|float $a, string|int|float $b, int $intermediateScale = 10): string
    {
        return bcdiv((string) $a, (string) $b, $intermediateScale);
    }

    public static function min(string|int|float $a, string|int|float $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function max(string|int|float $a, string|int|float $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function gte(string|int|float $a, string|int|float $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) >= 0;
    }

    private static function normalize(string|int|float $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }
}