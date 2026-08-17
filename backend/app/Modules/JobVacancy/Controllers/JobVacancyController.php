<?php

namespace App\Modules\JobVacancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\JobVacancy\Requests\StoreJobVacancyRequest;
use App\Modules\JobVacancy\Services\JobVacancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobVacancyController extends Controller
{
    public function __construct(
        private JobVacancyService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JobVacancy::class);

        $vacancies = JobVacancy::query()
            ->when($request->integer('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->with(['position', 'department', 'hiringManager', 'recruiter'])
            ->latest()
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Job Vacancy berhasil diambil.',
            'data' => $vacancies,
        ]);
    }

    public function store(StoreJobVacancyRequest $request): JsonResponse
    {
        $this->authorize('create', JobVacancy::class);

        $requisition = HiringRequisition::findOrFail($request->validated('hiring_requisition_id'));
        $hiringManager = Employee::findOrFail($request->validated('hiring_manager_employee_id'));
        $recruiter = Employee::findOrFail($request->validated('recruiter_employee_id'));

        $vacancy = $this->service->create($requisition, $hiringManager, $recruiter, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job Vacancy berhasil dibuat.',
            'data' => $vacancy,
        ], 201);
    }

    public function show(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('view', $jobVacancy);

        return response()->json([
            'success' => true,
            'message' => 'Detail Job Vacancy berhasil diambil',
            'data' => $jobVacancy->load(['hiringRequisition', 'position', 'department', 'hiringManager', 'recruiter', 'employmentType']),
        ]);
    }

    public function publish(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('publish', $jobVacancy);

        return $this->respondAfterTransition($this->service->publish($jobVacancy), 'dipublish');
    }

    public function pause(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('pause', $jobVacancy);

        return $this->respondAfterTransition($this->service->pause($jobVacancy), 'di-pause');
    }

    public function close(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('close', $jobVacancy);

        return $this->respondAfterTransition($this->service->close($jobVacancy), 'ditutup');
    }

    public function cancel(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('cancel', $jobVacancy);

        return $this->respondAfterTransition($this->service->cancel($jobVacancy), 'dibatalkan');
    }

    public function archive(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('archive', $jobVacancy);

        return $this->respondAfterTransition($this->service->archive($jobVacancy), 'diarsipkan');
    }

    private function respondAfterTransition(JobVacancy $vacancy, string $verb): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => "Job Vacancy berhasil {$verb}.",
            'data' => $vacancy,
        ]);
    }
}