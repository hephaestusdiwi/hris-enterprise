<?php 

namespace App\Modules\NewJoiner\Services;

use App\Models\User;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\NewJoiner\Enums\NewJoinerStatus;
use App\Modules\NewJoiner\Exceptions\NewJoinerValidationException;
use App\Modules\NewJoiner\Models\NewJoiner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewJoinerService
{
    public function send(Candidate $candidate, User $actor, ?int $expiresInDays = 7): NewJoiner
    {
        if ($candidate->status !== CandidateStatus::Hired) {
            throw new NewJoinerValidationException('New Joiner hanya bisa dikirim untuk Candidate berstatus Hired.');
        }

        $this->assertNoActiveNewJoiner($candidate);

        return DB::transaction(fn () => NewJoiner::create([
            'candidate_id' => $candidate->id,
            'token' => Str::random(48),
            'status' => NewJoinerStatus::Sent->value,
            'sent_by_user_id' => $actor->id,
            'sent_at' => now(),
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(NewJoiner $newJoiner, array $data): NewJoiner
    {
        if ($newJoiner->status !== NewJoinerStatus::Sent) {
            throw new NewJoinerValidationException('Form ini sudah pernah disubmit atau tidak valid.');
        }

        if ($newJoiner->expires_at && $newJoiner->expires_at->isPast()) {
            throw new NewJoinerValidationException('Link form ini sudah kedaluwarsa.');
        }

        $newJoiner->update([
            ...$data,
            'status' => NewJoinerStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        return $newJoiner->fresh();
    }

    public function markReadyForEmployee(NewJoiner $newJoiner): NewJoiner
    {
        if ($newJoiner->status !== NewJoinerStatus::Submitted) {
            throw new NewJoinerValidationException('New Joiner harus berstatus Submitted sebelum diproses ke Employee.');
        }

        if ($newJoiner->ready_for_employee_at) {
            throw new NewJoinerValidationException('New Joiner ini sudah pernah diproses ke tahap Employee.');
        }

        $newJoiner->update(['ready_for_employee_at' => now()]);

        return $newJoiner->fresh(); // Phase 7C konsumsi hasil ini — TIDAK membuat Employee di sini
    }

    private function assertNoActiveNewJoiner(Candidate $candidate): void
    {
        $exists = NewJoiner::where('candidate_id', $candidate->id)
            ->whereNull('ready_for_employee_at')
            ->exists();

        if ($exists) {
            throw new NewJoinerValidationException('Candidate ini sudah memiliki New Joiner yang masih aktif.');
        }
    }
}