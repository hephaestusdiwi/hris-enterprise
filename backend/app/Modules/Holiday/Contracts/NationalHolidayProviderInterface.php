<?php

namespace App\Modules\Holiday\Contracts;

use App\Modules\Holiday\DataTransferObjects\NationalHolidayData;
use App\Modules\Holiday\Exceptions\NationalHolidayProviderException;
use Illuminate\Support\Collection;

/**
 * Kontrak yang harus dipenuhi oleh setiap provider Hari Libur Nasional.
 *
 * Business logic Holiday Module (HolidayImportService, HolidayImportController)
 * HANYA boleh bergantung pada interface ini, tidak pernah langsung pada
 * implementasi provider (mis. NagerDateHolidayProvider). Ini memastikan
 * kalau provider diganti di kemudian hari, tidak ada logic bisnis yang berubah.
 */
interface NationalHolidayProviderInterface
{
    /**
     * Ambil daftar hari libur nasional untuk tahun tertentu.
     *
     * @return Collection<int, NationalHolidayData>
     *
     * @throws NationalHolidayProviderException Jika provider gagal diakses atau format respons tidak valid.
     */
    public function fetch(int $year): Collection;

    /**
     * Kunci unik provider, dipakai sebagai prefix external_id (mis. "nager").
     */
    public function key(): string;
}