<?php

namespace App\Modules\Payroll\Support;

class PayrollMath
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

    public static function div(string|int|float $a, string|int|float $b, int $scale = self::SCALE): string
    {
        return bcdiv((string) $a, (string) $b, $scale);
    }
}