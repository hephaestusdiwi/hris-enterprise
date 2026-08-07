<?php

namespace App\Modules\Bpjs\Contracts;

use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;

interface BpjsCalculationEngineInterface
{
    /**
     * Hitung kontribusi BPJS (Kesehatan, JHT, JKK, JKM) seorang employee untuk
     * satu titik waktu payroll tertentu. Murni kalkulasi — tidak menyimpan
     * apa pun. Konsumen (Payroll Generator, belum dibangun) yang menentukan
     * bagaimana hasilnya dipersist jadi payslip line.
     *
     * @param  array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>  $resolvedSalaryLines
     *         Output dari EmployeeSalaryResolver::resolveComponents() — dipakai sebagai basis wage base
     *         (SalaryComponent yang include_in_bpjs_base = true).
     * @return array<string, \App\Modules\Bpjs\DataTransferObjects\ResolvedBpjsContribution>
     *         Keyed by BpjsProgram->value. Program yang employee tidak terdaftar/tidak applicable
     *         tidak muncul di array (bukan null value, tapi memang absen).
     */
    public function calculateForEmployee(Employee $employee, Carbon $referenceDate, array $resolvedSalaryLines): array;
}