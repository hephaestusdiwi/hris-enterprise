<?php

namespace App\Http\Controllers;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\HiringRequisition\Services\HiringRequisitionApprovalService;
use App\Modules\Interview\Models\Interview;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\NewJoiner\Models\NewJoiner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Snapshot state Recruitment SAAT INI (jumlah per stage, upcoming interview,
 * dsb). SENGAJA bukan historical/trend reporting (Time-to-Hire, Time-to-Fill,
 * Acceptance Rate) — itu domain Fase D, butuh query timestamp yang lebih
 * berat dan biasanya punya halaman + filter tanggal sendiri.
 */
class RecruitmentOverviewController extends Controller
{
    public function __construct(
        private HiringRequisitionApprovalService $approvalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Recruitment overview berhasil diambil.',
            'data' => [
                'active_job_vacancies' => JobVacancy::where('status', JobVacancyStatus::Published->value)->count(),
                'candidates_by_status' => Candidate::query()
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status'),
                'upcoming_interviews' => Interview::query()
                    ->where('status', 'scheduled')
                    ->where('scheduled_at', '>=', now())
                    ->with(['candidate:id,full_name', 'jobVacancy:id,title', 'stage:id,name', 'interviewer:id,first_name,last_name'])
                    ->orderBy('scheduled_at')
                    ->limit(5)
                    ->get(),
                'new_joiners_pending' => NewJoiner::where('status', 'sent')->count(),
                'hiring_requisitions_pending_approval' => count($this->approvalService->pendingDecisionsForUser($user)),
            ],
        ]);
    }
}