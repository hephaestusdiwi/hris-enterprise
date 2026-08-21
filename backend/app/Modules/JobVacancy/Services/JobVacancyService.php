<?php

namespace App\Modules\JobVacancy\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Enums\HiringRequisitionStatus;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Enums\ApplicationMethod;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Exceptions\JobVacancyValidationException;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Support\Str;

class JobVacancyService
{
    /**
     * @param array{title: string, description: string, requirements?: ?string,
     *              employment_type_id?: ?int, visibility: string, application_deadline?: ?string} $data
     */
    public function create(
        HiringRequisition $requisition,
        Employee $hiringManager,
        Employee $recruiter,
        array $data,
    ): JobVacancy {
        $this->assertRequisitionOpenWithHeadcount($requisition);
        $this->assertNoActiveVacancy($requisition);

        $applicationMethod = ApplicationMethod::from($data['application_method']);

        if ($applicationMethod === ApplicationMethod::External && empty($data['external_apply_url'])) {
            throw new JobVacancyValidationException('external_apply_url wajib diisi kalau application_method adalah external.');
        }

        $vacancy = JobVacancy::create([
            'hiring_requisition_id' => $requisition->id,
            'company_id' => $requisition->company_id,
            'branch_id' => $requisition->branch_id,
            'department_id' => $requisition->department_id,
            'position_id' => $requisition->position_id,
            'hiring_manager_employee_id' => $hiringManager->id,
            'recruiter_employee_id' => $recruiter->id,
            'title' => $data['title'],
            'slug' => $this->generateUniqueSlug($data['title']),
            'description' => $data['description'],
            'requirements' => $data['requirements'] ?? null,
            'employment_type_id' => $data['employment_type_id'] ?? $requisition->employment_type_id,
            'visibility' => $data['visibility'],
            'application_method' => $applicationMethod->value,
            'external_apply_url' => $applicationMethod === ApplicationMethod::External ? $data['external_apply_url'] : null,
            'status' => JobVacancyStatus::Draft->value,
            'application_deadline' => $data['application_deadline'] ?? null,
        ]);

        return $vacancy->fresh();
    }

    public function publish(JobVacancy $vacancy): JobVacancy
    {
        if (! in_array($vacancy->status, [JobVacancyStatus::Draft, JobVacancyStatus::Paused], true)) {
            throw new JobVacancyValidationException('Job Vacancy hanya bisa dipublish dari status Draft atau Paused.');
        }

        $this->assertRequisitionOpenWithHeadcount($vacancy->hiringRequisition);
        $this->assertNoActiveVacancy($vacancy->hiringRequisition, excludingVacancyId: $vacancy->id);

        $vacancy->update([
            'status' => JobVacancyStatus::Published->value,
            'published_at' => $vacancy->published_at ?? now(),
        ]);

        return $vacancy->fresh();
    }

    public function pause(JobVacancy $vacancy): JobVacancy
    {
        if ($vacancy->status !== JobVacancyStatus::Published) {
            throw new JobVacancyValidationException('Hanya Job Vacancy berstatus Published yang bisa di-pause.');
        }

        $vacancy->update([
            'status' => JobVacancyStatus::Paused->value,
            'paused_at' => now(),
        ]);

        return $vacancy->fresh();
    }

    public function close(JobVacancy $vacancy): JobVacancy
    {
        if (! in_array($vacancy->status, [JobVacancyStatus::Published, JobVacancyStatus::Paused], true)) {
            throw new JobVacancyValidationException('Job Vacancy hanya bisa ditutup dari status Published atau Paused.');
        }

        $vacancy->update([
            'status' => JobVacancyStatus::Closed->value,
            'closed_at' => now(),
        ]);

        return $vacancy->fresh();
    }

    public function cancel(JobVacancy $vacancy): JobVacancy
    {
        if (in_array($vacancy->status, [JobVacancyStatus::Filled, JobVacancyStatus::Cancelled, JobVacancyStatus::Archived], true)) {
            throw new JobVacancyValidationException('Job Vacancy ini sudah dalam status final dan tidak bisa dibatalkan.');
        }

        $vacancy->update([
            'status' => JobVacancyStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        return $vacancy->fresh();
    }

    public function archive(JobVacancy $vacancy): JobVacancy
    {
        if (! in_array($vacancy->status, [JobVacancyStatus::Closed, JobVacancyStatus::Filled, JobVacancyStatus::Cancelled], true)) {
            throw new JobVacancyValidationException('Job Vacancy hanya bisa diarsipkan dari status Closed, Filled, atau Cancelled.');
        }

        $vacancy->update([
            'status' => JobVacancyStatus::Archived->value,
            'archived_at' => now(),
        ]);

        return $vacancy->fresh();
    }

    /**
     * Forward hook — dipanggil dari STEP Hire Candidate nanti, setelah headcount_filled
     * di HiringRequisition bertambah. TIDAK dipanggil di mana pun pada STEP ini.
     */
    public function markFilledIfRequisitionExhausted(HiringRequisition $requisition): void
    {
        if ($requisition->remainingHeadcount() > 0) {
            return;
        }

        JobVacancy::where('hiring_requisition_id', $requisition->id)
            ->whereIn('status', [JobVacancyStatus::Published->value, JobVacancyStatus::Paused->value])
            ->get()
            ->each(fn (JobVacancy $vacancy) => $vacancy->update([
                'status' => JobVacancyStatus::Filled->value,
                'filled_at' => now(),
            ]));
    }

    private function assertRequisitionOpenWithHeadcount(HiringRequisition $requisition): void
    {
        if ($requisition->status !== HiringRequisitionStatus::Open) {
            throw new JobVacancyValidationException('Hiring Requisition harus berstatus Open.');
        }

        if ($requisition->remainingHeadcount() <= 0) {
            throw new JobVacancyValidationException('Hiring Requisition ini sudah tidak memiliki sisa headcount.');
        }
    }

    private function assertNoActiveVacancy(HiringRequisition $requisition, ?int $excludingVacancyId = null): void
    {
        $exists = JobVacancy::where('hiring_requisition_id', $requisition->id)
            ->whereIn('status', [JobVacancyStatus::Published->value, JobVacancyStatus::Paused->value])
            ->when($excludingVacancyId, fn ($query) => $query->where('id', '!=', $excludingVacancyId))
            ->exists();

        if ($exists) {
            throw new JobVacancyValidationException('Sudah ada Job Vacancy aktif (Published/Paused) untuk Hiring Requisition ini.');
        }
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (JobVacancy::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}