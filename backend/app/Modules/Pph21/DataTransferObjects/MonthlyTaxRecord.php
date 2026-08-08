<?php

namespace App\Modules\Pph21\DataTransferObjects;

use Carbon\Carbon;

/**
 * Representasi 1 bulan histori payroll yang SUDAH terjadi dalam tahun pajak
 * berjalan — disuplai oleh caller (Payroll Generator, belum dibangun), BUKAN
 * di-query oleh Tax Engine sendiri. Engine tetap stateless — lihat Opsi A
 * di analisis desain.
 */
final class MonthlyTaxRecord
{
    public function __construct(
        public readonly Carbon $periodDate,
        public readonly string $grossIncome,
        public readonly string $pph21Withheld,
        public readonly string $pensionContribution = '0.00', // JHT porsi karyawan bulan itu (dari Bpjs engine)
    ) {
    }
}