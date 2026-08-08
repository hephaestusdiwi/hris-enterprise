<?php

namespace App\Modules\Pph21\Contracts;

use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;

interface TaxCalculationEngineInterface
{
    /**
     * Hitung PPh21 satu periode payroll (TER). Murni kalkulasi, tidak
     * menyimpan apa pun.
     *
     * @param  array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>  $resolvedSalaryLines
     * @param  array<string, \App\Modules\Bpjs\DataTransferObjects\ResolvedBpjsContribution>  $resolvedBpjsContributions
     *         Output BpjsCalculationEngine — dipakai buat cek JHT porsi karyawan (relevan di rekonsiliasi tahunan,
     *         tapi tetap diterima di sini supaya signature konsisten & bisa dipakai kalau suatu saat TER juga perlu).
     */
    public function calculateMonthly(
        Employee $employee,
        Carbon $referenceDate,
        array $resolvedSalaryLines,
        array $resolvedBpjsContributions = [],
    ): ?\App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;

    /**
     * Hitung rekonsiliasi masa pajak terakhir (Desember, atau bulan terakhir kerja).
     * $priorMonthsInYear WAJIB disuplai caller (Payroll Generator) — Engine ini
     * tidak query histori apa pun sendiri. Lihat DataTransferObjects\MonthlyTaxRecord.
     *
     * @param  array<int, \App\Modules\Pph21\DataTransferObjects\MonthlyTaxRecord>  $priorMonthsInYear
     * @param  array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>  $resolvedSalaryLines
     * @param  array<string, \App\Modules\Bpjs\DataTransferObjects\ResolvedBpjsContribution>  $resolvedBpjsContributions
     */
    public function calculateAnnualReconciliation(
        Employee $employee,
        Carbon $referenceDate,
        array $priorMonthsInYear,
        array $resolvedSalaryLines,
        array $resolvedBpjsContributions = [],
    ): ?\App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult;
}