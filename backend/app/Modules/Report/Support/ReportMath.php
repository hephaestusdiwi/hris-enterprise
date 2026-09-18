<?php

namespace App\Modules\Report\Support;

class ReportMath
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
}