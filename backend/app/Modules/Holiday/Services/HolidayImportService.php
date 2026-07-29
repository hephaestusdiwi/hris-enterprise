<?php

namespace App\Modules\Holiday\Services;

use App\Modules\Holiday\Contracts\NationalHolidayProviderInterface;
use App\Modules\Holiday\Models\Holiday;
use Illuminate\Support\Collection;

class HolidayImportService
{
    public function __construct(
        protected NationalHolidayProviderInterface $provider,
    ) {
    }

    /**
     * Preview: bandingkan data dari provider dengan yang sudah ada di tabel holidays,
     * tanpa menyimpan apa pun. Dipakai untuk tampilan "mana yang baru, mana yang update".
     *
     * @return Collection<int, array>
     */
    public function preview(int $year): Collection
    {
        $items = $this->provider->fetch($year);

        $existing = Holiday::withTrashed()
            ->whereIn('external_id', $items->pluck('externalId'))
            ->get()
            ->keyBy('external_id');

        return $items->map(function ($item) use ($existing) {
            $current = $existing->get($item->externalId);

            $status = 'new';
            if ($current) {
                $status = match (true) {
                    $current->source === 'manual' => 'manual-locked', // HR sudah edit, tidak akan ditimpa
                    $current->name === $item->name && $current->date->format('Y-m-d') === $item->date => 'unchanged',
                    default => 'update',
                };
            }

            return [
                'external_id' => $item->externalId,
                'date' => $item->date,
                'name' => $item->name,
                'type' => $item->type,
                'status' => $status,
                'existing_name' => $current?->name,
            ];
        })->values();
    }

    /**
     * UPSERT hari libur nasional ke tabel holidays.
     *
     * Aturan:
     * - Dicocokkan lewat external_id (idempoten, tidak membuat duplikasi walau nama berubah di provider).
     * - Holiday yang sudah pernah di-edit manual oleh HR (source = 'manual') TIDAK ditimpa lagi.
     * - Selalu company_id = null (berlaku untuk semua company), sesuai sifat National Holiday.
     *
     * @param array<int, string>|null $externalIds Subset yang dikonfirmasi user di step preview. Null = semua.
     * @return Collection<int, Holiday>
     */
    public function import(int $year, ?array $externalIds = null): Collection
    {
        $items = $this->provider->fetch($year);

        if ($externalIds !== null) {
            $items = $items->whereIn('externalId', $externalIds);
        }

        return $items->map(function ($item) {
            $existing = Holiday::withTrashed()->firstWhere('external_id', $item->externalId);

            if ($existing && $existing->source === 'manual') {
                // HR sudah mengambil alih data ini secara manual — jangan ditimpa.
                return $existing;
            }

            return Holiday::updateOrCreate(
                ['external_id' => $item->externalId],
                [
                    'company_id' => null,
                    'date' => $item->date,
                    'name' => $item->name,
                    'type' => $item->type,
                    'is_active' => true,
                    'source' => 'import',
                ]
            );
        })->values();
    }
}