<?php

namespace App\Modules\Bpjs\Enums;

enum BpjsCostBearer: string
{
    case DefaultPolicy = 'default';       // ikut pengaturan company (CompanyBpjsSetting)
    case CompanyBorne = 'company_borne';  // porsi karyawan ikut ditanggung company (take-home tidak dipotong)
    case EmployeeBorne = 'employee_borne'; // split normal, karyawan bayar porsinya sendiri
    case NotParticipating = 'not_participating'; // khusus Jht — company tidak ikut program ini utk employee tsb

    public function label(): string
    {
        return match ($this) {
            self::DefaultPolicy => 'Default (ikut pengaturan company)',
            self::CompanyBorne => 'Ditanggung Company',
            self::EmployeeBorne => 'Ditanggung Karyawan',
            self::NotParticipating => 'Tidak Diikutkan',
        };
    }
}