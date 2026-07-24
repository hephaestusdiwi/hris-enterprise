<?php

namespace App\Modules\WorkingSchedule\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface;
use App\Modules\WorkingSchedule\Exceptions\WorkingScheduleChangeException;
use App\Modules\WorkingSchedule\Models\WorkingSchedule;
use App\Modules\WorkingSchedule\Models\WorkingScheduleScheduledChange;
use App\Modules\WorkingSchedule\Services\WorkingScheduleChangeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SchedulerController extends Controller
{
    public function __construct(
        private WorkingScheduleResolverInterface $resolver,
        private WorkingScheduleChangeService $changeService,
    ) {
    }

    public function index(Request $request)
    {
        $employees = Employee::query()
            ->with(['company', 'branch', 'department', 'position'])
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('position_id'), fn ($q, $v) => $q->where('position_id', $v))
            ->when($request->query('job_level_id'), fn ($q, $v) => $q->where('job_level_id', $v))
            ->when($request->query('employment_status_id'), fn ($q, $v) => $q->where('employment_status_id', $v))
            ->latest()
            ->paginate(20);

        $employees->getCollection()->transform(function (Employee $employee) {
            $currentScheduleId = $this->resolver->resolveWorkingScheduleId($employee);
            $nextChange = $this->changeService->resolveNextChange($employee);

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'company' => $employee->company,
                'branch' => $employee->branch,
                'department' => $employee->department,
                'position' => $employee->position,
                'current_working_schedule' => $currentScheduleId
                    ? WorkingSchedule::find($currentScheduleId)
                    : null,
                'next_working_schedule' => $nextChange ? [
                    'working_schedule' => $nextChange->workingSchedule,
                    'effective_date' => $nextChange->effective_date->toDateString(),
                ] : null,
            ];
        });

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $employees]);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'working_schedule_id' => ['required', 'exists:working_schedules,id'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $change = $this->changeService->schedule(
            'employee',
            $validated['employee_id'],
            $validated['working_schedule_id'],
            Carbon::parse($validated['effective_date']),
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Working Schedule berhasil dijadwalkan',
            'data' => $change,
        ], 201);
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'working_schedule_id' => ['required', 'exists:working_schedules,id'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $effectiveDate = Carbon::parse($validated['effective_date']);
        $changes = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            $changes[] = $this->changeService->schedule(
                'employee',
                $employeeId,
                $validated['working_schedule_id'],
                $effectiveDate,
                $request->user()->id,
                $validated['notes'] ?? null,
            );
        }

        return response()->json([
            'success' => true,
            'message' => count($changes).' employee berhasil dijadwalkan',
            'data' => $changes,
        ], 201);
    }

    public function cancelScheduledChange(WorkingScheduleScheduledChange $scheduledChange)
    {
        try {
            $this->changeService->cancel($scheduledChange);

            return response()->json([
                'success' => true,
                'message' => 'Scheduled change berhasil dibatalkan',
                'data' => null,
            ]);
        } catch (WorkingScheduleChangeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}