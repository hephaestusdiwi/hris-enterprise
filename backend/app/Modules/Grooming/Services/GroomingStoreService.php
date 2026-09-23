<?php

namespace App\Modules\Grooming\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Concerns\SavesGroomingPhoto;
use App\Modules\Grooming\Enums\GroomingResult;
use App\Modules\Grooming\Enums\GroomingType;
use App\Modules\Grooming\Exceptions\GroomingValidationException;
use App\Modules\Grooming\Models\GroomingStandard;
use App\Modules\Grooming\Models\GroomingStoreSubmission;
use Illuminate\Support\Facades\DB;

class GroomingStoreService
{
    use SavesGroomingPhoto;

    public function __construct(
        private GroomingStandardService $standardService,
    ) {
    }

    public function activeStandard(): GroomingStandard
    {
        return $this->standardService->resolveActive(GroomingType::Store);
    }

    /**
     * @param array<int, array{grooming_standard_item_id: int, result: string, note?: ?string, photo?: ?string}> $answers
     */
    public function submit(Branch $branch, Employee $submitter, array $answers): GroomingStoreSubmission
    {
        $standard = $this->activeStandard();
        $mandatoryItemIds = $standard->activeItems->where('mandatory', true)->pluck('id');
        $answersByItemId = collect($answers)->keyBy('grooming_standard_item_id');

        $missing = $mandatoryItemIds->diff($answersByItemId->keys());
        if ($missing->isNotEmpty()) {
            throw new GroomingValidationException('Semua item checklist wajib diisi.');
        }

        $overallResult = GroomingResult::Pass;

        foreach ($standard->activeItems as $item) {
            $answer = $answersByItemId->get($item->id);
            if (! $answer) {
                continue;
            }

            // Validasi kelengkapan dulu (foto + catatan) sebelum ada apapun
            // yang ditulis ke DB — supaya submission gagal utuh, bukan
            // setengah-setengah kalau item ke-3 dari 5 ternyata kurang foto.
            if ($item->requires_photo && empty($answer['photo'] ?? null)) {
                throw new GroomingValidationException(
                    "Foto wajib dilampirkan untuk item \"{$item->name}\"."
                );
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

        return DB::transaction(function () use ($branch, $submitter, $standard, $answersByItemId, $overallResult) {
            $submission = GroomingStoreSubmission::create([
                'branch_id' => $branch->id,
                'submitted_by_employee_id' => $submitter->id,
                'grooming_standard_id' => $standard->id,
                'overall_result' => $overallResult->value,
                'submitted_at' => now(),
            ]);

            foreach ($standard->activeItems as $item) {
                $answer = $answersByItemId->get($item->id);
                if (! $answer) {
                    continue;
                }

                $photoPath = null;
                if (! empty($answer['photo'] ?? null)) {
                    $photoPath = $this->saveGroomingPhoto(
                        $answer['photo'],
                        "grooming-store/{$branch->id}/{$submission->id}"
                    );
                }

                $submission->answers()->create([
                    'grooming_standard_item_id' => $item->id,
                    'result' => $answer['result'],
                    'note' => $answer['note'] ?? null,
                    'photo_path' => $photoPath,
                ]);
            }

            return $submission->load('answers.item');
        });
    }
}