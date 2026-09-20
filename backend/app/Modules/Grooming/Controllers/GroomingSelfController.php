<?php

namespace App\Modules\Grooming\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Enums\GroomingResult;
use App\Modules\Grooming\Models\GroomingSelfSubmission;
use App\Modules\Grooming\Requests\StoreGroomingSelfSubmissionRequest;
use App\Modules\Grooming\Services\GroomingSelfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroomingSelfController extends Controller
{
    public function __construct(
        private GroomingSelfService $service,
    ) {
    }

    public function activeStandard(): JsonResponse
    {
        $this->authorize('create', GroomingSelfSubmission::class);

        return response()->json([
            'success' => true,
            'message' => 'Grooming Self Standard aktif berhasil diambil.',
            'data' => $this->service->activeStandard(),
        ]);
    }

    public function store(StoreGroomingSelfSubmissionRequest $request): JsonResponse
    {
        $this->authorize('create', GroomingSelfSubmission::class);

        $employee = $request->user()->employee;
        $submission = $this->service->submit($employee, $request->validated('answers'), $request->validated('photo'));

        return response()->json([
            'success' => true,
            'message' => 'Grooming Self berhasil disubmit.',
            'data' => $submission,
        ], 201);
    }

    public function myHistory(Request $request): JsonResponse
    {
        $this->authorize('viewOwnHistory', GroomingSelfSubmission::class);

        $submissions = GroomingSelfSubmission::query()
            ->where('employee_id', $request->user()->employee?->id ?? 0)
            ->with('standard')
            ->latest('submitted_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Histori Grooming Self berhasil diambil.',
            'data' => $submissions,
        ]);
    }

    /**
     * Ringkasan buat kartu overview monitoring — Total Employee/Submitted/
     * Not Submitted/PASS/NOT PASS/Compliance % untuk SATU tanggal. "Submitted"
     * dihitung dari submission TERAKHIR tiap employee di tanggal itu (selaras
     * dengan aturan "submission terakhir = status hari ini").
     */
    public function monitoringSummary(Request $request): JsonResponse
    {
        $this->authorize('viewMonitoring', GroomingSelfSubmission::class);

        $date = $request->date('date') ?? today();
        $totalEmployees = Employee::whereNull('resign_date')->count();

        $latestPerEmployeeToday = GroomingSelfSubmission::whereDate('submitted_at', $date)
            ->orderByDesc('submitted_at')
            ->get()
            ->unique('employee_id');

        $submittedCount = $latestPerEmployeeToday->count();
        $passCount = $latestPerEmployeeToday->where('overall_result', GroomingResult::Pass)->count();
        $notPassCount = $latestPerEmployeeToday->where('overall_result', GroomingResult::NotPass)->count();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan monitoring Grooming Self berhasil diambil.',
            'data' => [
                'date' => $date->toDateString(),
                'total_employees' => $totalEmployees,
                'submitted_count' => $submittedCount,
                'not_submitted_count' => max(0, $totalEmployees - $submittedCount),
                'pass_count' => $passCount,
                'not_pass_count' => $notPassCount,
                'compliance_percent' => $totalEmployees > 0 ? round(($passCount / $totalEmployees) * 100, 1) : null,
            ],
        ]);
    }

    /**
     * Monitoring cross-employee — buat Supervisor/Manager/HR.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewMonitoring', GroomingSelfSubmission::class);

        $submissions = GroomingSelfSubmission::query()
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->string('result')->toString(), fn ($q, $v) => $q->where('overall_result', $v))
            ->when($request->date('date_from'), fn ($q, $v) => $q->whereDate('submitted_at', '>=', $v))
            ->when($request->date('date_to'), fn ($q, $v) => $q->whereDate('submitted_at', '<=', $v))
            ->with(['employee:id,first_name,last_name', 'branch:id,name'])
            ->latest('submitted_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Monitoring Grooming Self berhasil diambil.',
            'data' => $submissions,
        ]);
    }

    public function show(GroomingSelfSubmission $groomingSelfSubmission): JsonResponse
    {
        $this->authorize('view', $groomingSelfSubmission);

        return response()->json([
            'success' => true,
            'message' => 'Detail Grooming Self berhasil diambil.',
            'data' => $groomingSelfSubmission->load(['employee:id,first_name,last_name', 'branch:id,name', 'standard', 'answers.item']),
        ]);
    }
}