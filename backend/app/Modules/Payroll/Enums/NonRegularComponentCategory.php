<?php

namespace App\Modules\Payroll\Enums;

enum NonRegularComponentCategory: string
{
    case Bonus = 'bonus';
    case Incentive = 'incentive';
    case Commission = 'commission';
    case OneTimeEarning = 'one_time_earning';
    case OneTimeDeduction = 'one_time_deduction';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Bonus => 'Bonus',
            self::Incentive => 'Incentive',
            self::Commission => 'Commission',
            self::OneTimeEarning => 'One-Time Earning',
            self::OneTimeDeduction => 'One-Time Deduction',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Arah default kategori ini (earning/deduction). Dipakai sebagai default
     * saat component dibuat — Adjustment sengaja tidak masuk sini karena
     * arahnya ditentukan eksplisit oleh pembuat component (bisa penambah
     * ATAU pengurang tergantung kasus), bukan hard-coded oleh kategori.
     */
    public function defaultIsAddition(): ?bool
    {
        return match ($this) {
            self::Bonus, self::Incentive, self::Commission, self::OneTimeEarning => true,
            self::OneTimeDeduction => false,
            self::Adjustment => null,
        };
    }
}