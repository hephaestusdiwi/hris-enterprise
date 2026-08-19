<?php

namespace App\Modules\CashAdvance\Support;

/**
 * Salinan pola LoanMath/ReimbursementMath (bcmath, tanpa float) supaya
 * kalkulasi uang di Cash Advance konsisten presisinya dengan module Finance
 * lain. Tidak diekstrak jadi shared class supaya tidak menyentuh module lain.
 */
class CashAdvanceMath
{
    private const SCALE = 2;

    public static function add(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcadd($a, $b, $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcsub($a, $b, $scale);
    }

    public static function gte(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) >= 0;
    }
}