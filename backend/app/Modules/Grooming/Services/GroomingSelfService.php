<?php

namespace App\Modules\Grooming\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Enums\GroomingResult;
use App\Modules\Grooming\Enums\GroomingType;
use App\Modules\Grooming\Exceptions\GroomingValidationException;
use App\Modules\Grooming\Models\GroomingSelfSubmission;
use App\Modules\Grooming\Models\GroomingStandard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GroomingSelfService
{
    public function __construct(
        private GroomingStandardService $standardService,
    ) {
    }

    public function activeStandard(): GroomingStandard
    {
        return $this->standardService->resolveActive(GroomingType::Self);
    }

    /**
     * @param array<int, array{grooming_standard_item_id: int, result: string, note?: ?string}> $answers
     */
    public function submit(Employee $employee, array $answers, string $photoBase64): GroomingSelfSubmission
    {
        $standard = $this->activeStandard();
        $mandatoryItemIds = $standard->activeItems->where('mandatory', true)->pluck('id');
        $answersByItemId = collect($answers)->keyBy('grooming_standard_item_id');

        // Semua item mandatory WAJIB dijawab — jangan diam-diam anggap PASS
        // kalau employee skip salah satu.
        $missing = $mandatoryItemIds->diff($answersByItemId->keys());
        if ($missing->isNotEmpty()) {
            throw new GroomingValidationException('Semua item checklist wajib diisi.');
        }

        $overallResult = GroomingResult::Pass;

        foreach ($standard->activeItems as $item) {
            $answer = $answersByItemId->get($item->id);
            if (! $answer) {
                continue; // item non-mandatory yang tidak dijawab, boleh dilewati
            }

            $result = GroomingResult::from($answer['result']);

            if ($result === GroomingResult::NotPass) {
                if ($item->requires_note_on_fail && empty($answer['note'] ?? null)) {
                    throw new GroomingValidationException(
                        "Catatan wajib diisi untuk item \"{$item->name}\" yang NOT PASS."
                    );
                }
                if ($item->mandatory) {
                    $overallResult = GroomingResult::NotPass;
                }
            }
        }

        $photoPath = $this->savePhoto($employee, $photoBase64);

        return DB::transaction(function () use ($employee, $standard, $answersByItemId, $overallResult, $photoPath) {
            $submission = GroomingSelfSubmission::create([
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'grooming_standard_id' => $standard->id,
                'overall_result' => $overallResult->value,
                'photo_path' => $photoPath,
                'submitted_at' => now(),
            ]);

            foreach ($standard->activeItems as $item) {
                $answer = $answersByItemId->get($item->id);
                if (! $answer) {
                    continue;
                }

                $submission->answers()->create([
                    'grooming_standard_item_id' => $item->id,
                    'result' => $answer['result'],
                    'note' => $answer['note'] ?? null,
                ]);
            }

            return $submission->load('answers.item');
        });
    }

    /**
     * Simpan foto base64 sebagai webp — pola identik dengan
     * AttendanceService::resolveAndSavePhoto(), disk public, biar konsisten
     * dengan bagaimana foto Face Attendance sudah ditangani di project ini.
     */
    private function savePhoto(Employee $employee, string $photoBase64): string
    {
        $raw = preg_replace('/^data:image\/\w+;base64,/', '', $photoBase64);
        $decoded = base64_decode($raw);
        $image = @imagecreatefromstring($decoded);

        if (! $image) {
            throw new GroomingValidationException('Format foto tidak valid.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $filename = "grooming-self/{$employee->id}/".now()->timestamp.'.webp';
        Storage::disk('public')->makeDirectory("grooming-self/{$employee->id}");
        imagewebp($image, Storage::disk('public')->path($filename), 85);
        imagedestroy($image);

        return $filename;
    }
}