<?php

namespace App\Modules\Holiday\NationalHolidayProviders;

use App\Modules\Holiday\Contracts\NationalHolidayProviderInterface;
use App\Modules\Holiday\DataTransferObjects\NationalHolidayData;
use App\Modules\Holiday\Exceptions\NationalHolidayProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provider Hari Libur Nasional menggunakan Nager.Date (https://date.nager.at).
 *
 * Ini SATU-SATUNYA kelas yang tahu bentuk request/response Nager.Date.
 * Jika provider ini diganti di masa depan, cukup buat kelas baru yang
 * implements NationalHolidayProviderInterface — HolidayImportService,
 * HolidayImportController, dan modul lain (Attendance, Leave, Payroll,
 * Overtime) tidak perlu diubah sama sekali.
 */
class NagerDateHolidayProvider implements NationalHolidayProviderInterface
{
    protected string $baseUrl;
    protected string $countryCode;

    public function __construct(?string $baseUrl = null, ?string $countryCode = null)
    {
        $this->baseUrl = $baseUrl ?? config('holiday.providers.nager.base_url', 'https://date.nager.at/api/v3');
        $this->countryCode = $countryCode ?? config('holiday.providers.nager.country_code', 'ID');
    }

    public function key(): string
    {
        return 'nager';
    }

    public function fetch(int $year): Collection
    {
        try {
            $response = Http::timeout(10)
                ->retry(2, 200)
                ->get("{$this->baseUrl}/PublicHolidays/{$year}/{$this->countryCode}");
        } catch (Throwable $e) {
            Log::error('NagerDateHolidayProvider: request gagal', [
                'year' => $year,
                'message' => $e->getMessage(),
            ]);

            throw new NationalHolidayProviderException(
                "Gagal menghubungi provider hari libur nasional untuk tahun {$year}. Silakan coba lagi."
            );
        }

        if ($response->failed()) {
            throw new NationalHolidayProviderException(
                "Provider hari libur nasional mengembalikan error (HTTP {$response->status()}) untuk tahun {$year}."
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new NationalHolidayProviderException(
                "Format respons provider hari libur nasional tidak sesuai untuk tahun {$year}."
            );
        }

        return collect($payload)
            ->filter(fn (array $item) => in_array('Public', $item['types'] ?? [], true))
            ->map(function (array $item) {
                $date = $item['date'];
                $name = $item['localName'] ?? ($item['name'] ?? 'Hari Libur Nasional');

                return new NationalHolidayData(
                    date: $date,
                    name: $name,
                    externalId: "{$this->key()}:{$this->countryCode}:{$date}",
                    type: 'national',
                );
            })
            ->values();
    }
}