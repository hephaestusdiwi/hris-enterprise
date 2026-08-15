<?php

namespace App\Modules\Offering\Services;

use App\Models\User;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Services\CandidateService;
use App\Modules\Offering\Enums\OfferingStatus;
use App\Modules\Offering\Exceptions\OfferingValidationException;
use App\Modules\Offering\Models\Offering;
use Illuminate\Support\Facades\DB;

class OfferingService
{
    public function __construct(
        private CandidateService $candidateService,
    ) {
    }

    /**
     * @param array{proposed_start_date: string, proposed_salary?: ?float, compensation_notes?: ?string, notes?: ?string} $data
     */
    public function create(Candidate $candidate, User $actor, array $data): Offering
    {
        if ($candidate->status !== CandidateStatus::Selected) {
            throw new OfferingValidationException('Offering hanya bisa dibuat untuk Candidate berstatus Selected.');
        }

        $this->assertNoActiveOffering($candidate);

        return DB::transaction(function () use ($candidate, $actor, $data) {
            $offering = Offering::create([
                'candidate_id' => $candidate->id,
                'proposed_start_date' => $data['proposed_start_date'],
                'proposed_salary' => $data['proposed_salary'] ?? null,
                'compensation_notes' => $data['compensation_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => OfferingStatus::Draft->value,
                'created_by_user_id' => $actor->id,
            ]);

            $this->candidateService->transitionStatus($candidate, CandidateStatus::Offering, $actor->id, 'Offering dibuat.');

            return $offering->fresh();
        });
    }

    public function update(Offering $offering, array $data): Offering
    {
        if ($offering->status !== OfferingStatus::Draft) {
            throw new OfferingValidationException('Offering hanya bisa diedit selagi masih Draft.');
        }

        $offering->update([
            'proposed_start_date' => $data['proposed_start_date'] ?? $offering->proposed_start_date,
            'proposed_salary' => $data['proposed_salary'] ?? $offering->proposed_salary,
            'compensation_notes' => $data['compensation_notes'] ?? $offering->compensation_notes,
            'notes' => $data['notes'] ?? $offering->notes,
        ]);

        return $offering->fresh();
    }

    public function send(Offering $offering, User $actor): Offering
    {
        if ($offering->status !== OfferingStatus::Draft) {
            throw new OfferingValidationException('Offering hanya bisa dikirim selagi masih Draft.');
        }

        return DB::transaction(function () use ($offering, $actor) {
            $offering->update([
                'status' => OfferingStatus::Sent->value,
                'sent_at' => now(),
            ]);

            $this->candidateService->transitionStatus($offering->candidate, CandidateStatus::Offered, $actor->id, 'Offering dikirim ke Candidate.');

            return $offering->fresh();
        });
    }

    public function respond(Offering $offering, OfferingStatus $response, ?string $notes = null): Offering
    {
        if ($offering->status !== OfferingStatus::Sent) {
            throw new OfferingValidationException('Offering harus berstatus Sent sebelum bisa dicatat responsnya.');
        }

        if (! in_array($response, [OfferingStatus::Accepted, OfferingStatus::Declined], true)) {
            throw new OfferingValidationException('Response hanya boleh Accepted atau Declined.');
        }

        $offering->update([
            'status' => $response->value,
            'notes' => $notes ?? $offering->notes,
            'responded_at' => now(),
        ]);

        // Sengaja TIDAK menyentuh Candidate.status — konsekuensi Accept/Decline
        // (jadi Hired atau proses lain) adalah scope Phase 7B, bukan di sini.

        return $offering->fresh();
    }

    public function withdraw(Offering $offering): Offering
    {
        if (! in_array($offering->status, [OfferingStatus::Draft, OfferingStatus::Sent], true)) {
            throw new OfferingValidationException('Offering ini sudah final, tidak bisa ditarik.');
        }

        $offering->update(['status' => OfferingStatus::Withdrawn->value]);

        return $offering->fresh();
    }

    private function assertNoActiveOffering(Candidate $candidate): void
    {
        $exists = Offering::where('candidate_id', $candidate->id)
            ->whereIn('status', [OfferingStatus::Draft->value, OfferingStatus::Sent->value])
            ->exists();

        if ($exists) {
            throw new OfferingValidationException('Candidate ini sudah punya Offering aktif.');
        }
    }
}