<?php

namespace App\Modules\Candidate\Services;

use App\Modules\Candidate\Enums\CandidateSource;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Exceptions\CandidateValidationException;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Models\CandidateStageHistory;
use App\Modules\JobVacancy\Enums\ApplicationMethod;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Models\User;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\Offering\Enums\OfferingStatus;
use Illuminate\Support\Facades\DB;

class CandidateService
{
    /**
     * @param array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     source: string,
     *     cv_path?: ?string
     * } $data
     */
    public function apply(JobVacancy $vacancy, array $data): Candidate
    {
        if ($vacancy->status !== JobVacancyStatus::Published) {
            throw new CandidateValidationException(
                'Job Vacancy ini sedang tidak membuka lamaran.'
            );
        }

        if ($vacancy->application_method !== ApplicationMethod::Internal) {
            throw new CandidateValidationException(
                'Job Vacancy ini menerima lamaran lewat platform eksternal, bukan lewat sistem ini.'
            );
        }

        $this->assertNoDuplicateActiveApplication($vacancy, $data['email']);

        return DB::transaction(function () use ($vacancy, $data) {
            $candidate = Candidate::create([
                'job_vacancy_id' => $vacancy->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'source' => $data['source'],
                'cv_path' => $data['cv_path'] ?? null,
                'status' => CandidateStatus::Applied->value,
                'applied_at' => now(),
            ]);

            $this->recordStageChange(
                $candidate,
                null,
                CandidateStatus::Applied,
                null,
                'Kandidat melamar.'
            );

            return $candidate->fresh();
        });
    }

    public function reconsider(
        Candidate $original,
        JobVacancy $targetVacancy,
        User $actor,
        ?string $notes = null
    ): Candidate {
        return DB::transaction(function () use ($original, $targetVacancy, $actor, $notes) {
            // Reload dengan lock — semua guard di bawah ini WAJIB pakai instance ini,
            // bukan $original yang di-pass dari luar transaction.
            $original = Candidate::lockForUpdate()->findOrFail($original->id);

            if ($original->status !== CandidateStatus::Hold) {
                throw new CandidateValidationException(
                    'Hanya Candidate berstatus Hold yang bisa direconsider.'
                );
            }

            if ($targetVacancy->status !== JobVacancyStatus::Published) {
                throw new CandidateValidationException(
                    'Job Vacancy tujuan sedang tidak membuka lamaran.'
                );
            }

            if ($targetVacancy->application_method !== ApplicationMethod::Internal) {
                throw new CandidateValidationException(
                    'Job Vacancy tujuan menerima lamaran lewat platform eksternal.'
                );
            }

            $hasActiveReconsideration = $original
                ->reconsiderations()
                ->where('job_vacancy_id', $targetVacancy->id)
                ->where('status', '!=', CandidateStatus::Rejected->value)
                ->exists();

            if ($hasActiveReconsideration) {
                throw new CandidateValidationException(
                    'Candidate has already been reconsidered for this vacancy.'
                );
            }

            $newCandidate = Candidate::create([
                'job_vacancy_id' => $targetVacancy->id,
                'full_name' => $original->full_name,
                'email' => $original->email,
                'phone' => $original->phone,
                'source' => CandidateSource::TalentPool->value,
                'cv_path' => $original->cv_path,
                'status' => CandidateStatus::Applied->value,
                'reconsidered_from_candidate_id' => $original->id,
                'applied_at' => now(),
            ]);

            $this->recordStageChange(
                $newCandidate,
                null,
                CandidateStatus::Applied,
                $actor->id,
                $notes ?? 'Direconsider dari talent pool.'
            );

            return $newCandidate->fresh();
        });
    }

    private function assertNoDuplicateActiveApplication(
        JobVacancy $vacancy,
        string $email
    ): void {
        $exists = Candidate::where('job_vacancy_id', $vacancy->id)
            ->where('email', $email)
            ->whereNotIn('status', [
                CandidateStatus::Rejected->value,
                CandidateStatus::Hired->value,
            ])
            ->exists();

        if ($exists) {
            throw new CandidateValidationException(
                'Email ini sudah pernah melamar ke Job Vacancy ini dan masih dalam proses.'
            );
        }
    }

    public function transitionStatus(
        Candidate $candidate,
        CandidateStatus $to,
        ?int $changedByUserId,
        ?string $notes = null,
    ): Candidate {
        return DB::transaction(function () use (
            $candidate,
            $to,
            $changedByUserId,
            $notes
        ) {
            $from = $candidate->status;

            $candidate->update([
                'status' => $to->value,
                'held_at' => $to === CandidateStatus::Hold
                    ? now()
                    : $candidate->held_at,
                'hired_at' => $to === CandidateStatus::Hired
                    ? now()
                    : $candidate->hired_at,
                'rejected_at' => $to === CandidateStatus::Rejected
                    ? now()
                    : $candidate->rejected_at,
            ]);

            $this->recordStageChange(
                $candidate,
                $from,
                $to,
                $changedByUserId,
                $notes
            );

            return $candidate->fresh();
        });
    }

    /**
     * Tandai Candidate sebagai Selected.
     *
     * Hanya Candidate dengan status Interview yang boleh
     * dipindahkan ke status Selected.
     */
    public function select(
        Candidate $candidate,
        User $actor,
        ?string $notes = null
    ): Candidate {
        if ($candidate->status !== CandidateStatus::Interview) {
            throw new CandidateValidationException(
                'Hanya Candidate berstatus Interview yang bisa ditandai Selected.'
            );
        }

        return $this->transitionStatus(
            $candidate,
            CandidateStatus::Selected,
            $actor->id,
            $notes ?? 'Candidate dinyatakan selected.'
        );
    }

    public function holdManually(Candidate $candidate, User $actor, ?string $notes = null): Candidate
    {
        if ($candidate->status === CandidateStatus::Hold) {
            throw new CandidateValidationException('Candidate ini sudah berada di Talent Pool (Hold).');
        }

        if ($candidate->status === CandidateStatus::Offering) {
            throw new CandidateValidationException('Candidate ini memiliki Offering Draft aktif — withdraw Offering terlebih dahulu sebelum di-Hold.');
        }

        if ($candidate->status === CandidateStatus::Offered) {
            throw new CandidateValidationException('Candidate ini sudah menerima Offering yang terkirim — tidak bisa di-Hold.');
        }

        if ($candidate->status === CandidateStatus::Hired) {
            throw new CandidateValidationException('Candidate ini sudah Hired — tidak bisa di-Hold.');
        }

        if ($candidate->status === CandidateStatus::Rejected) {
            throw new CandidateValidationException('Candidate yang sudah Rejected tidak dapat dipindahkan ke Talent Pool.');
        }

        return $this->transitionStatus(
            $candidate,
            CandidateStatus::Hold,
            $actor->id,
            $notes ?? 'Dipindahkan ke Talent Pool secara manual.'
        );
    }

    private function recordStageChange(
        Candidate $candidate,
        ?CandidateStatus $from,
        CandidateStatus $to,
        ?int $changedByUserId,
        ?string $notes,
    ): void {
        CandidateStageHistory::create([
            'candidate_id' => $candidate->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by_user_id' => $changedByUserId,
            'notes' => $notes,
            'changed_at' => now(),
        ]);
    }

    public function hire(Candidate $candidate, User $actor, ?string $notes = null): Candidate
    {
        if ($candidate->status === CandidateStatus::Hired) {
            throw new CandidateValidationException('Candidate ini sudah Hired sebelumnya.');
        }
        if ($candidate->status !== CandidateStatus::Offered) {
            throw new CandidateValidationException('Candidate harus berstatus Offered sebelum bisa di-Hired.');
        }
        $accepted = $candidate->offerings()->where('status', OfferingStatus::Accepted->value)->exists();
        if (! $accepted) {
            throw new CandidateValidationException('Candidate ini belum memiliki Offering berstatus Accepted.');
        }
        return $this->transitionStatus($candidate, CandidateStatus::Hired, $actor->id, $notes ?? 'Candidate dinyatakan Hired.');
    }
}