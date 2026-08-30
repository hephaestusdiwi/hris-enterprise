<?php

namespace App\Modules\Interview\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Employee\Models\Employee;
use App\Modules\Interview\Enums\InterviewResult;
use App\Modules\Interview\Models\Interview;
use App\Modules\Interview\Models\InterviewStage;
use App\Modules\Interview\Requests\CancelInterviewRequest;
use App\Modules\Interview\Requests\CompleteInterviewRequest;
use App\Modules\Interview\Requests\ScheduleInterviewRequest;
use App\Modules\Interview\Services\InterviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct(
        private InterviewService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Interview::class);

        $user = $request->user();

        $interviews = Interview::query()
            ->when($request->integer('candidate_id'), fn ($q, $v) => $q->where('candidate_id', $v))
            ->when($request->integer('job_vacancy_id'), fn ($q, $v) => $q->where('job_vacancy_id', $v))
            ->when($request->integer('interview_stage_id'), fn ($q, $v) => $q->where('interview_stage_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when(
                // Interviewer biasa (tanpa permission 'view interviews') CUMA boleh lihat
                // interview miliknya sendiri — abaikan interviewer_employee_id dari query
                // apapun yang dikirim, supaya tidak bisa dipakai buat intip jadwal orang lain.
                ! $user->can('view interviews'),
                fn ($q) => $q->where('interviewer_employee_id', $user->employee?->id ?? 0),
                fn ($q) => $q->when($request->integer('interviewer_employee_id'), fn ($q2, $v) => $q2->where('interviewer_employee_id', $v)),
            )
            ->with(['candidate', 'jobVacancy', 'stage', 'interviewer'])
            ->latest('scheduled_at')
            ->paginate();

        return response()->json(['success' => true, 'message' => 'Daftar Interview berhasil diambil.', 'data' => $interviews]);
    }

    public function store(ScheduleInterviewRequest $request): JsonResponse
    {
        $this->authorize('create', Interview::class);

        $candidate = Candidate::findOrFail($request->validated('candidate_id'));
        $stage = InterviewStage::findOrFail($request->validated('interview_stage_id'));
        $interviewer = Employee::findOrFail($request->validated('interviewer_employee_id'));

        $interview = $this->service->schedule(
            $candidate,
            $stage,
            $interviewer,
            $request->user(),
            Carbon::parse($request->validated('scheduled_at')),
            $request->validated('notes'),
        );

        return response()->json(['success' => true, 'message' => 'Interview berhasil dijadwalkan.', 'data' => $interview], 201);
    }

    public function show(Interview $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return response()->json([
            'success' => true,
            'message' => 'Detail Interview berhasil diambil.',
            'data' => $interview->load(['candidate', 'jobVacancy', 'stage', 'interviewer', 'scheduledBy']),
        ]);
    }

    public function start(Interview $interview): JsonResponse
    {
        $this->authorize('start', $interview);

        return response()->json(['success' => true, 'message' => 'Interview dimulai.', 'data' => $this->service->start($interview)]);
    }

    public function complete(Interview $interview, CompleteInterviewRequest $request): JsonResponse
    {
        $this->authorize('complete', $interview);

        $result = InterviewResult::from($request->validated('result'));

        $interview = $this->service->complete(
            $interview,
            $result,
            $request->validated('score'),
            $request->validated('notes'),
            $request->validated('recommendation'),
        );

        return response()->json(['success' => true, 'message' => 'Interview berhasil diselesaikan.', 'data' => $interview]);
    }

    public function cancel(Interview $interview, CancelInterviewRequest $request): JsonResponse
    {
        $this->authorize('cancel', $interview);

        return response()->json([
            'success' => true,
            'message' => 'Interview dibatalkan.',
            'data' => $this->service->cancel($interview, $request->validated('notes')),
        ]);
    }
}