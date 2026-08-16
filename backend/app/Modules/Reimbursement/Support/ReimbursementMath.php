<?php

namespace App\Modules\Reimbursement\Support;

/**
 * Kalkulasi uang menggunakan BCMath,
 * tanpa float.
 */
class ReimbursementMath
{
    private const SCALE = 2;

    public static function add(
        string $a,
        string $b,
        int $scale = self::SCALE
    ): string {
        return bcadd($a, $b, $scale);
    }

    public static function sub(
        string $a,
        string $b,
        int $scale = self::SCALE
    ): string {
        return bcsub($a, $b, $scale);
    }

    public static function gte(
        string $a,
        string $b
    ): bool {
        return bccomp(
            $a,
            $b,
            self::SCALE
        ) >= 0;
    }
}