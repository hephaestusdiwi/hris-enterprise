<?php

namespace App\Modules\Payroll\Enums;

enum PayrollDisbursementStatus: string
{
    case Generated = 'generated';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Digenerate',
            self::Sent => 'Sudah Dikirim ke Bank',
            self::Confirmed => 'Terkonfirmasi',
            self::Failed => 'Gagal',
        };
    }
}
