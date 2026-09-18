<?php

namespace App\Modules\Grooming\Services;

use App\Modules\Grooming\Enums\GroomingStandardStatus;
use App\Modules\Grooming\Enums\GroomingType;
use App\Modules\Grooming\Exceptions\GroomingValidationException;
use App\Modules\Grooming\Models\GroomingStandard;
use App\Modules\Grooming\Models\GroomingStandardItem;
use Illuminate\Support\Facades\DB;

/**
 * ATURAN INTI (jangan dilanggar): sekali sebuah GroomingStandard berstatus
 * 'active' (pernah dipublish), baris itu — dan seluruh item di dalamnya —
 * TIDAK BOLEH DIEDIT LAGI. Perubahan kebijakan HARUS lewat createNewVersion()
 * (baris baru), bukan update baris lama. Ini yang menjaga histori submission
 * tetap akurat walau standard berubah berkali-kali.
 */
class GroomingStandardService
{
    /**
     * @param array{name: string, description?: ?string, effective_date: string,
     *              items: array<int, array{name: string, description?: ?string,
     *              mandatory?: bool, requires_note_on_fail?: bool,
     *              requires_photo?: bool}>} $data
     */
    public function create(GroomingType $type, array $data, ?int $createdByEmployeeId): GroomingStandard
    {
        return DB::transaction(function () use ($type, $data, $createdByEmployeeId) {
            $standard = GroomingStandard::create([
                'type' => $type->value,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version_number' => 1,
                'effective_date' => $data['effective_date'],
                'status' => GroomingStandardStatus::Draft->value,
                'created_by_employee_id' => $createdByEmployeeId,
            ]);

            $this->syncItems($standard, $data['items']);

            return $standard->load('items');
        });
    }

    /**
     * Bikin versi baru dari standard yang sudah ada (aktif/archived/draft lain)
     * — item-nya di-copy sebagai titik awal, TIDAK mengubah baris asal sama
     * sekali. Versi baru selalu mulai sebagai draft.
     */
    public function createNewVersion(GroomingStandard $previous, array $data): GroomingStandard
    {
        return DB::transaction(function () use ($previous, $data) {
            $standard = GroomingStandard::create([
                'type' => $previous->type->value,
                'name' => $data['name'] ?? $previous->name,
                'description' => $data['description'] ?? $previous->description,
                'version_number' => $previous->version_number + 1,
                'effective_date' => $data['effective_date'],
                'status' => GroomingStandardStatus::Draft->value,
                'created_by_employee_id' => $data['created_by_employee_id'] ?? null,
            ]);

            $items = $data['items'] ?? $previous->items->map(fn ($i) => [
                'name' => $i->name,
                'description' => $i->description,
                'mandatory' => $i->mandatory,
                'requires_note_on_fail' => $i->requires_note_on_fail,
                'requires_photo' => $i->requires_photo,
            ])->all();

            $this->syncItems($standard, $items);

            return $standard->load('items');
        });
    }

    /**
     * @param array{name?: string, description?: ?string, effective_date?: string,
     *              items?: array} $data
     */
    public function updateDraft(GroomingStandard $standard, array $data): GroomingStandard
    {
        $this->assertIsDraft($standard);

        return DB::transaction(function () use ($standard, $data) {
            $standard->update(array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'effective_date' => $data['effective_date'] ?? null,
            ], fn ($v) => $v !== null));

            if (isset($data['items'])) {
                $this->syncItems($standard, $data['items']);
            }

            return $standard->fresh('items');
        });
    }

    /**
     * Aktifkan standard ini. Kalau ada standard lain dengan TYPE SAMA yang
     * sedang aktif, otomatis di-archive (cuma boleh 1 versi aktif per type).
     */
    public function activate(GroomingStandard $standard): GroomingStandard
    {
        if ($standard->status !== GroomingStandardStatus::Draft) {
            throw new GroomingValidationException('Hanya standard berstatus Draft yang bisa diaktifkan.');
        }

        if ($standard->items()->count() === 0) {
            throw new GroomingValidationException('Standard tidak bisa diaktifkan tanpa item checklist.');
        }

        return DB::transaction(function () use ($standard) {
            GroomingStandard::where('type', $standard->type->value)
                ->where('status', GroomingStandardStatus::Active->value)
                ->update(['status' => GroomingStandardStatus::Archived->value]);

            $standard->update(['status' => GroomingStandardStatus::Active->value]);

            return $standard->fresh('items');
        });
    }

    public function archive(GroomingStandard $standard): GroomingStandard
    {
        if ($standard->status !== GroomingStandardStatus::Active) {
            throw new GroomingValidationException('Hanya standard berstatus Active yang bisa di-archive.');
        }

        $standard->update(['status' => GroomingStandardStatus::Archived->value]);

        return $standard->fresh('items');
    }

    public function resolveActive(GroomingType $type): GroomingStandard
    {
        $standard = GroomingStandard::where('type', $type->value)
            ->where('status', GroomingStandardStatus::Active->value)
            ->with('activeItems')
            ->first();

        if (! $standard) {
            $label = $type === GroomingType::Self ? 'Grooming Self' : 'Grooming Store';
            throw new GroomingValidationException(
                "Belum ada Grooming Standard aktif untuk {$label}. Hubungi HR untuk mengaktifkan standard terlebih dahulu."
            );
        }

        return $standard;
    }

    private function assertIsDraft(GroomingStandard $standard): void
    {
        if ($standard->status !== GroomingStandardStatus::Draft) {
            throw new GroomingValidationException(
                'Standard yang sudah Active/Archived tidak bisa diedit. Buat versi baru untuk mengubah kebijakan.'
            );
        }
    }

    private function syncItems(GroomingStandard $standard, array $items): void
    {
        $standard->items()->delete();

        foreach ($items as $index => $item) {
            GroomingStandardItem::create([
                'grooming_standard_id' => $standard->id,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'mandatory' => $item['mandatory'] ?? true,
                'requires_note_on_fail' => $item['requires_note_on_fail'] ?? true,
                'requires_photo' => $item['requires_photo'] ?? false,
                'sort_order' => $item['sort_order'] ?? $index,
                'is_active' => $item['is_active'] ?? true,
            ]);
        }
    }
}