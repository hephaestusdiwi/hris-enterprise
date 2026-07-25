<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Exports\AttendanceReportExport;
use App\Modules\Attendance\Services\AttendanceReportService;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    public function __construct(private AttendanceReportService $reportService)
    {
    }

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
            ->orderBy('first_name')
            ->paginate(20);

        $summary = $this->reportService->summarize($employees->getCollection(), $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'data' => $summary,
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function daily(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $rows = $this->reportService->dailyRecap(
            $employee,
            Carbon::parse($validated['date_from'])->startOfDay(),
            Carbon::parse($validated['date_to'])->endOfDay(),
        );

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $employees = Employee::query()
            ->when($validated['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($validated['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($validated['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('first_name')
            ->get();

        $summary = $this->reportService->summarize($employees, $dateFrom, $dateTo);
        $filename = 'attendance-report-'.$dateFrom->toDateString().'-to-'.$dateTo->toDateString().'.xlsx';

        return Excel::download(new AttendanceReportExport($summary), $filename);
    }
}