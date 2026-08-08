<?php

namespace App\Modules\Pph21\DataTransferObjects;

use App\Modules\Pph21\Enums\TaxMethod;

final class AnnualReconciliationResult
{
    public function __construct(
        public readonly TaxMethod $taxMethodApplied,
        public readonly int $taxYear,
        public readonly string $totalGrossAnnual,
        public readonly string $positionCostDeduction,   // biaya jabatan
        public readonly string $pensionDeduction,        // total JHT porsi karyawan setahun
        public readonly string $ptkpAmount,
        public readonly string $netAnnualIncome,
        public readonly string $pkp,                     // penghasilan kena pajak, sudah dibulatkan ke bawah per seribu
        public readonly string $annualTaxPasal17,
        public readonly string $totalWithheldPriorMonths,
        public readonly string $finalPeriodAdjustment,    // positif = kurang potong (masih harus dipotong), negatif = lebih potong (dikembalikan)
        public readonly string $grossUpAllowance,         // 0 kalau bukan GrossUp
        public readonly bool $noTaxIdSurchargeApplied,
    ) {
    }
}