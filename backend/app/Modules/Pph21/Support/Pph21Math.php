<?php

namespace App\Modules\Pph21\Support;

class Pph21Math
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

    public static function min(string|int|float $a, string|int|float $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) <= 0 ? (string) $a : (string) $b;
    }

    public static function max(string|int|float $a, string|int|float $b): string
    {
        return bccomp((string) $a, (string) $b, self::SCALE) >= 0 ? (string) $a : (string) $b;
    }

    public static function gt(string|int|float $a, string|int|float $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) > 0;
    }

    /**
     * PKP dibulatkan ke bawah ke ribuan rupiah terdekat [Regulasi Pemerintah].
     */
    public static function floorToThousand(string $value): string
    {
        $thousands = bcdiv($value, '1000', 0); // bcdiv scale 0 = truncate/floor ke integer
        return bcmul($thousands, '1000', 2);
    }
}