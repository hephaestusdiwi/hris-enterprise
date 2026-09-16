<?php

namespace App\Http\Controllers;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\Offering\Enums\OfferingStatus;
use App\Modules\Offering\Models\Offering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Laporan "Time to Hire" ala Mekari Talenta — VERIFIED dari
 * help-center.talenta.co: 1 halaman berisi Time to Hire, Time to Fill,
 * dan Acceptance Rate sekaligus (bukan 3 halaman terpisah).
 *
 * Definisi (dari dokumentasi resmi):
 * - Time to Hire  : durasi dari Candidate masuk pipeline (applied_at)
 *                   sampai di-mark Hired (hired_at).
 * - Time to Fill  : durasi dari Job Vacancy dibuka (published_at) sampai
 *                   kandidat MENERIMA offering (Offering.responded_at
 *                   dengan status Accepted) — BUKAN sampai hired_at.
 *                   Method "Individual" (per kandidat), sesuai dokumentasi.
 * - Acceptance Rate: Offering berstatus Accepted dibagi Offering yang
 *                    sudah direspon (Accepted + Declined) — Withdrawn/
 *                    Expired sengaja tidak dihitung, itu bukan keputusan
 *                    kandidat.
 */
class RecruitmentReportController extends Controller
{
    public function timeToHire(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');
        $jobVacancyId = $request->integer('job_vacancy_id');

        $candidates = Candidate::query()
            ->whereNotNull('hired_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('hired_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('hired_at', '<=', $dateTo))
            ->when($jobVacancyId, fn ($q, $v) => $q->where('job_vacancy_id', $v))
            ->with([
                'jobVacancy.company:id,name',
                'jobVacancy.branch:id,name',
                'jobVacancy.recruiter:id,first_name,last_name',
                'interviews:id,candidate_id,interview_stage_id',
                'offerings' => fn ($q) => $q->where('status', OfferingStatus::Accepted->value)->latest('responded_at'),
            ])
            ->orderByDesc('hired_at')
            ->get();

        $rows = $candidates->map(function (Candidate $candidate) {
            $vacancy = $candidate->jobVacancy;
            $acceptedOffering = $candidate->offerings->first();

            return [
                'candidate' => ['id' => $candidate->id, 'full_name' => $candidate->full_name],
                'job_vacancy' => $vacancy ? ['id' => $vacancy->id, 'title' => $vacancy->title] : null,
                'recruiter' => $vacancy?->recruiter,
                'company' => $vacancy?->company,
                'branch' => $vacancy?->branch,
                'published_at' => $vacancy?->published_at,
                'total_stages' => $candidate->interviews->pluck('interview_stage_id')->unique()->count(),
                'hired_at' => $candidate->hired_at,
                'time_to_hire_days' => $candidate->applied_at && $candidate->hired_at
                    ? $candidate->applied_at->diffInDays($candidate->hired_at)
                    : null,
                'time_to_fill_days' => $vacancy?->published_at && $acceptedOffering?->responded_at
                    ? $vacancy->published_at->diffInDays($acceptedOffering->responded_at)
                    : null,
            ];
        });

        // Acceptance Rate DIHITUNG TERPISAH dari seluruh Offering yang sudah
        // direspon dalam scope filter yang sama — TIDAK di-scope ke hired
        // candidates saja, karena Accepted belum tentu sudah di-"Hire" HR.
        $respondedOfferings = Offering::query()
            ->whereIn('status', [OfferingStatus::Accepted->value, OfferingStatus::Declined->value])
            ->when($dateFrom, fn ($q) => $q->whereDate('responded_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('responded_at', '<=', $dateTo))
            ->when($jobVacancyId, fn ($q, $v) => $q->whereHas('candidate', fn ($q2) => $q2->where('job_vacancy_id', $v)))
            ->get();

        $acceptedCount = $respondedOfferings->where('status', OfferingStatus::Accepted)->count();
        $respondedCount = $respondedOfferings->count();

        return response()->json([
            'success' => true,
            'message' => 'Time to Hire Report berhasil diambil.',
            'data' => [
                'summary' => [
                    'avg_time_to_hire_days' => round((float) $rows->pluck('time_to_hire_days')->filter(fn ($v) => $v !== null)->avg(), 1) ?: null,
                    'avg_time_to_fill_days' => round((float) $rows->pluck('time_to_fill_days')->filter(fn ($v) => $v !== null)->avg(), 1) ?: null,
                    'acceptance_rate_percent' => $respondedCount > 0 ? round(($acceptedCount / $respondedCount) * 100, 1) : null,
                    'total_hired' => $rows->count(),
                ],
                'rows' => $rows->values(),
            ],
        ]);
    }
}