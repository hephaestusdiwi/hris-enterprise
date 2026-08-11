<?php 

namespace App\Modules\Interview\Services;

use App\Models\User;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Employee\Models\Employee;
use App\Modules\Interview\Enums\InterviewResult;
use App\Modules\Interview\Enums\InterviewStatus;
use App\Modules\Interview\Exceptions\InterviewValidationException;
use App\Modules\Interview\Models\Interview;
use App\Modules\Interview\Models\InterviewStage;
use Carbon\Carbon;

class InterviewService
{
    public function schedule(
        Candidate $candidate,
        InterviewStage $stage,
        Employee $interviewer,
        User $actor,
        Carbon $scheduledAt,
        ?string $notes = null,
    ): Interview {
        if ($candidate->status !== CandidateStatus::Interview) {
            throw new InterviewValidationException('Candidate harus berstatus Interview sebelum interview stage ini bisa dijadwalkan.');
        }

        $this->assertInterviewerEligible($interviewer);
        $this->assertNoActiveInterviewForStage($candidate, $stage);

        return Interview::create([
            'candidate_id' => $candidate->id,
            'job_vacancy_id' => $candidate->job_vacancy_id,
            'interview_stage_id' => $stage->id,
            'interviewer_employee_id' => $interviewer->id,
            'scheduled_by_user_id' => $actor->id,
            'scheduled_at' => $scheduledAt,
            'status' => InterviewStatus::Scheduled->value,
            'notes' => $notes,
        ])->fresh();
    }

    public function start(Interview $interview): Interview
    {
        if ($interview->status !== InterviewStatus::Scheduled) {
            throw new InterviewValidationException('Interview hanya bisa dimulai dari status Scheduled.');
        }

        $interview->update(['status' => InterviewStatus::InProgress->value]);

        return $interview->fresh();
    }

    public function complete(
        Interview $interview,
        InterviewResult $result,
        ?int $score = null,
        ?string $notes = null,
        ?string $recommendation = null,
    ): Interview {
        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::InProgress], true)) {
            throw new InterviewValidationException('Interview ini sudah tidak aktif (Completed/Cancelled).');
        }

        $interview->update([
            'status' => InterviewStatus::Completed->value,
            'result' => $result->value,
            'score' => $score,
            'notes' => $notes ?? $interview->notes,
            'recommendation' => $recommendation,
            'completed_at' => now(),
        ]);

        return $interview->fresh();
    }

    public function cancel(Interview $interview, ?string $notes = null): Interview
    {
        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::InProgress], true)) {
            throw new InterviewValidationException('Interview ini sudah tidak aktif (Completed/Cancelled).');
        }

        $interview->update([
            'status' => InterviewStatus::Cancelled->value,
            'notes' => $notes ?? $interview->notes,
            'cancelled_at' => now(),
        ]);

        return $interview->fresh();
    }

    private function assertInterviewerEligible(Employee $interviewer): void
    {
        if (! $interviewer->user || ! $interviewer->user->can('conduct interviews')) {
            throw new InterviewValidationException('Employee ini tidak memiliki wewenang sebagai interviewer.');
        }
    }

    private function assertNoActiveInterviewForStage(Candidate $candidate, InterviewStage $stage): void
    {
        $exists = Interview::where('candidate_id', $candidate->id)
            ->where('interview_stage_id', $stage->id)
            ->whereIn('status', [InterviewStatus::Scheduled->value, InterviewStatus::InProgress->value])
            ->exists();

        if ($exists) {
            throw new InterviewValidationException('Candidate ini sudah memiliki interview aktif untuk stage yang sama.');
        }
    }
}