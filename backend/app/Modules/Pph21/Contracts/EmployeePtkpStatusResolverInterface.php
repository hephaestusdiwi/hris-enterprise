<?php

namespace App\Modules\Pph21\Contracts;

use App\Modules\Pph21\Models\EmployeePtkpStatus;

interface EmployeePtkpStatusResolverInterface
{
    /**
     * Cari status PTKP employee yang berlaku di tax_year tertentu — ambil versi
     * dengan tax_year <= target terbesar (mirror pola "PTKP Status Adjustment"
     * Talenta: perubahan cuma berlaku mulai tahun pajak berikutnya).
     */
    public function resolveForTaxYear(int $employeeId, int $taxYear): ?EmployeePtkpStatus;
}