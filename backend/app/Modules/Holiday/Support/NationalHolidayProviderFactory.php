<?php

namespace App\Modules\Holiday\Support;

use App\Modules\Holiday\Contracts\NationalHolidayProviderInterface;
use App\Modules\Holiday\NationalHolidayProviders\NagerDateHolidayProvider;
use InvalidArgumentException;

/**
 * Titik tunggal untuk memutuskan implementasi provider mana yang dipakai,
 * berdasarkan config('holiday.default_provider').
 *
 * Jika nanti pindah provider: tambahkan branch match baru di sini dan
 * tambahkan config-nya di config/holiday.php. Tidak ada file lain yang perlu diubah.
 */
class NationalHolidayProviderFactory
{
    public static function make(?string $driver = null): NationalHolidayProviderInterface
    {
        $driver = $driver ?? config('holiday.default_provider', 'nager');

        return match ($driver) {
            'nager' => new NagerDateHolidayProvider(),
            default => throw new InvalidArgumentException("Provider hari libur nasional [{$driver}] tidak dikenali."),
        };
    }
}