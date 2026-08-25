<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Requests\MarkAbsenceAsTimeOffRequest;
use App\Modules\Attendance\Requests\ReverseAbsenceDeductionRequest;
use App\Modules\Attendance\Services\AbsenceDeductionService;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveRequest\Exceptions\LeaveRequestValidationException;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use App\Modules\LeaveType\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceAbsenceDeductionController extends Controller
{
    public function __construct(private AbsenceDeductionService $absenceDeductionService)
    {
    }

    /**
     * Filter set sengaja identik dengan AttendanceReportController::index()
     * (company_id/branch_id/department_id/employee_id) -- convention yang
     * sama, bukan yang baru.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $employees = Employee::query()
            ->when($validated['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($validated['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($validated['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($validated['employee_id'] ?? null, fn ($q, $v) => $q->where('id', $v))
            ->get();

        $absences = $this->absenceDeductionService->listAbsences($employees, $dateFrom, $dateTo);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $absences]);
    }

    public function markAsTimeOff(MarkAbsenceAsTimeOffRequest $request)
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));
        $leaveType = LeaveType::findOrFail($request->validated('leave_type_id'));
        $date = Carbon::parse($request->validated('date'))->startOfDay();

        try {
            $leaveRequest = $this->absenceDeductionService->markAsTimeOff($employee, $leaveType, $date);

            return response()->json([
                'success' => true,
                'message' => 'Absence berhasil ditandai sebagai Time-Off',
                'data' => $leaveRequest,
            ], 201);
        } catch (LeaveRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    /**
     * "Time-Off / Deduction History" -- filter set sengaja sama dengan
     * index() (company_id/branch_id/department_id/employee_id), reuse
     * convention yang sama, plus date_from/date_to terhadap start_date.
     */
    public function deductions(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $employees = Employee::query()
            ->when($validated['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($validated['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($validated['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($validated['employee_id'] ?? null, fn ($q, $v) => $q->where('id', $v))
            ->get();

        $deductions = $this->absenceDeductionService->listDeductions($employees, $dateFrom, $dateTo);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $deductions]);
    }

    public function reverse(ReverseAbsenceDeductionRequest $request, LeaveRequest $leaveRequest)
    {
        try {
            $reversed = $this->absenceDeductionService->reverse(
                $leaveRequest,
                $request->user(),
                $request->validated('reason'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Absence Deduction berhasil di-reverse',
                'data' => $reversed,
            ]);
        } catch (LeaveRequestValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }
}