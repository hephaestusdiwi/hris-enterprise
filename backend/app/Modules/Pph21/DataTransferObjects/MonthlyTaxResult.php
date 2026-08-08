<?php

namespace App\Modules\Pph21\DataTransferObjects;

use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;

final class MonthlyTaxResult
{
    public function __construct(
        public readonly TaxMethod $taxMethodApplied,
        public readonly TerCategory $terCategory,
        public readonly string $taxableGrossIncome,   // penghasilan bruto kena pajak bulan ini (belum termasuk tunjangan pajak gross-up)
        public readonly string $terRatePercentageUsed,
        public readonly string $pph21Amount,           // nominal PPh21 bulan ini
        public readonly string $takeHomePayDeduction,  // yang benar2 mengurangi THP (0 kalau Netto/GrossUp)
        public readonly string $grossUpAllowance,      // tunjangan pajak yang ditambahkan ke gross (0 kalau bukan GrossUp)
        public readonly bool $noTaxIdSurchargeApplied,
        public readonly ?int $rateSourceId,            // id baris TerRateBracket yang dipakai
    ) {
    }
}