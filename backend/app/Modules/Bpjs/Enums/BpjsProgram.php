<?php

namespace App\Modules\Bpjs\Enums;

enum BpjsProgram: string
{
    case Kesehatan = 'kesehatan';
    case Jht = 'jht';
    case Jkk = 'jkk';
    case Jkm = 'jkm';
    // case Jp = 'jp'; tinggal aktifkan case ini + seed BpjsRateConfig program=jp,
    // engine & resolver tidak perlu diubah sama sekali.

    public function label(): string
    {
        return match ($this) {
            self::Kesehatan => 'BPJS Kesehatan',
            self::Jht => 'Jaminan Hari Tua (JHT)',
            self::Jkk => 'Jaminan Kecelakaan Kerja (JKK)',
            self::Jkm => 'Jaminan Kematian (JKM)',
        };
    }

    /**
     * JKK & JKM secara regulasi selalu 100% ditanggung perusahaan — karyawan
     * tidak pernah patungan di dua program ini, makanya tidak ada cost bearer
     * override untuk keduanya (beda dengan Kesehatan & JHT).
     */
    public function hasEmployeeContribution(): bool
    {
        return match ($this) {
            self::Kesehatan, self::Jht => true,
            self::Jkk, self::Jkm => false,
        };
    }
}