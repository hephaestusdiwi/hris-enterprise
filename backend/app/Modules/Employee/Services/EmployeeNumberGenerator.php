<?php 

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Str;

class EmployeeNumberGenerator
{
    /**
     * Dipindahkan dari App\Modules\NewJoiner\Services\NewJoinerConversionService
     * (tempat sementara sebelumnya, Phase 7C) — sekarang satu-satunya generator,
     * format TIDAK diubah, cuma pindah lokasi.
     */
    public function generate(int $companyId): string
    {
        do {
            $number = sprintf('EMP-%d-%s', $companyId, strtoupper(Str::random(6)));
        } while (Employee::where('employee_number', $number)->exists());

        return $number;
    }
}