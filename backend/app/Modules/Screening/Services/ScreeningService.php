<?php

namespace App\Modules\Screening\Services;

use App\Models\User;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Services\CandidateService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Screening\Enums\ScreeningResult;
use App\Modules\Screening\Enums\ScreeningStatus;
use App\Modules\Screening\Exceptions\ScreeningValidationException;
use App\Modules\Screening\Models\Screening;

class ScreeningService
{
    public function __construct(
        private CandidateService $candidateService,
    ){
    }

    public function start(Candidate $candidate, Employee $reviewer, User $actor, ?string $notes = null): Screening
    {
        if ($candidate->status !== CandidateStatus::Applied) {
            throw new ScreeningValidationException('Screening hanya bisa dimulai untuk Candidate berstatus Applied.');
        }

        $this->assertNoActiveScreening($candidate);

        $screening = Screening::create([
            'candidate_id' => $candidate->id,
            'reviewer_employee_id' => $reviewer->id,
            'status' => ScreeningStatus::Pending->value,
            'notes' => $notes,
        ]);

        $this->candidateService->transitionStatus($candidate, CandidateStatus::Screening, $actor->id, 'Screening dimulai.');

        return $screening->fresh();
    }

    public function decide(Screening $screening, ScreeningResult $result, User $actor, ?string $notes = null): Screening
    {
        if ($screening->status !== ScreeningStatus::Pending) {
            throw new ScreeningValidationException('Screening ini sudah diputuskan sebelumnya.');
        }

        $screening->update([
            'status' => ScreeningStatus::Completed->value,
            'result' => $result->value,
            'notes' => $notes ?? $screening->notes,
            'reviewed_at' => now(),
        ]);

        $nextStatus = match ($result) {
            ScreeningResult::Passed => CandidateStatus::Interview,
            ScreeningResult::Failed => CandidateStatus::Rejected,
            ScreeningResult::Hold => CandidateStatus::Hold,
        };

        $this->candidateService->transitionStatus(
            $screening->candidate,
            $nextStatus,
            $actor->id,
            "Screening selesai: {$result->value}." . ($notes ? " {$notes}" : ''),
        );

        return $screening->fresh();
    }

    private function assertNoActiveScreening(Candidate $candidate): void
    {
        $exists = Screening::where('candidate_id', $candidate->id)
            ->where('status', ScreeningStatus::Pending->value)
            ->exists();

        if ($exists) {
            throw new ScreeningValidationException('Candidate ini sudah memiliki Screening yang masih pending.');
        }
    }
}