<?php

namespace App\Modules\LeaveRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Employee\Models\Employee;
use App\Modules\Holiday\Models\Holiday;
use App\Modules\LeaveRequest\Enums\LeaveApprovalRequestStatus;
use App\Modules\LeaveRequest\Models\LeaveApprovalStepDecision;
use App\Modules\LeaveRequest\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveCalendarController extends Controller
{
    public function __construct(private ApprovalStepApproverResolver $approverResolver)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'status' => ['nullable', 'array'],
            'status.*' => ['in:pending,approved,rejected'],
        ]);

        $monthStart = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $companyId = $validated['company_id'] ?? null;
        $statuses = $validated['status'] ?? ['pending', 'approved'];

        $scope = $this->resolveScope($request->user());

        $holidays = Holiday::where('is_active', true)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->when($companyId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'type']);

        $leaveRequests = LeaveRequest::query()
            ->whereIn('status', $statuses)
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->when($scope['mode'] !== 'all', fn ($q) => $q->whereIn('employee_id', $scope['employee_ids']))
            ->when($validated['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($validated['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->whereHas('employee', function ($q) use ($companyId, $validated) {
                $q->when($companyId, fn ($q2) => $q2->where('company_id', $companyId))
                    ->when($validated['branch_id'] ?? null, fn ($q2, $v) => $q2->where('branch_id', $v))
                    ->when($validated['department_id'] ?? null, fn ($q2, $v) => $q2->where('department_id', $v))
                    ->when($validated['position_id'] ?? null, fn ($q2, $v) => $q2->where('position_id', $v));
            })
            ->with([
                'employee:id,first_name,last_name,photo_path,department_id',
                'employee.department:id,name',
                'leaveType:id,name,color',
            ])
            ->get();

        $leaves = $leaveRequests->map(fn (LeaveRequest $lr) => [
            'id' => $lr->id,
            'employee' => [
                'id' => $lr->employee->id,
                'name' => trim("{$lr->employee->first_name} {$lr->employee->last_name}"),
                'photo_url' => $lr->employee->photo_url,
                'department' => $lr->employee->department ? [
                    'id' => $lr->employee->department->id,
                    'name' => $lr->employee->department->name,
                ] : null,
            ],
            'leave_type' => $lr->leaveType,
            'status' => $lr->status->value ?? $lr->status,
            'start_date' => $lr->start_date->toDateString(),
            'end_date' => $lr->end_date->toDateString(),
            'is_half_day' => $lr->is_half_day,
            'total_days' => $lr->total_days,
        ]);

        $departmentIds = $leaveRequests->pluck('employee.department_id')->filter()->unique()->values();

        $departmentHeadcount = Employee::whereIn('department_id', $departmentIds)
            ->whereNull('resign_date')
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'holidays' => $holidays,
                'leaves' => $leaves,
                'department_headcount' => $departmentHeadcount,
                'scope_mode' => $scope['mode'],
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $scope = $this->resolveScope($request->user());
        $companyId = $validated['company_id'] ?? null;
        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(7);

        $base = function () use ($scope, $companyId) {
            return LeaveRequest::query()
                ->when($scope['mode'] !== 'all', fn ($q) => $q->whereIn('employee_id', $scope['employee_ids']))
                ->whereHas('employee', fn ($q) => $q->when($companyId, fn ($q2) => $q2->where('company_id', $companyId)));
        };

        $onLeaveToday = $base()
            ->where('status', 'approved')
            ->where('start_date', '<=', $today->toDateString())
            ->where('end_date', '>=', $today->toDateString())
            ->count();

        $pendingCount = $base()->where('status', 'pending')->count();

        $onSickLeaveToday = $base()
            ->where('status', 'approved')
            ->where('start_date', '<=', $today->toDateString())
            ->where('end_date', '>=', $today->toDateString())
            ->whereHas('leaveType', fn ($q) => $q->where('name', 'like', '%sick%'))
            ->count();

        $upcomingThisWeek = $base()
            ->where('status', 'approved')
            ->whereBetween('start_date', [$today->copy()->addDay()->toDateString(), $weekEnd->toDateString()])
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'on_leave_today' => $onLeaveToday,
                'pending_leave' => $pendingCount,
                'on_sick_leave_today' => $onSickLeaveToday,
                'upcoming_this_week' => $upcomingThisWeek,
            ],
        ]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $scope = $this->resolveScope($request->user());

        if ($scope['mode'] !== 'all' && ! in_array($leaveRequest->employee_id, $scope['employee_ids'], true)) {
            abort(403, 'Anda tidak punya akses ke leave request ini.');
        }

        $leaveRequest->load([
            'employee:id,first_name,last_name,photo_path,department_id,manager_employee_id',
            'employee.department:id,name',
            'employee.manager:id,first_name,last_name',
            'leaveType',
            'leaveBalance',
        ]);

        $approvalRequest = $leaveRequest->approvalRequest;
        $history = [];
        $canDecide = false;
        $currentDecisionId = null;
        $waitingFor = null;

        if ($approvalRequest) {
            $decisions = LeaveApprovalStepDecision::where('leave_approval_request_id', $approvalRequest->id)
                ->with('approvalStep')
                ->orderBy('sequence')
                ->get();

            $deciderIds = $decisions->pluck('decided_by_user_id')->filter()->unique()->values();
            $deciders = User::whereIn('id', $deciderIds)->get(['id', 'name'])->keyBy('id');

            $history = $decisions->map(fn ($d) => [
                'sequence' => $d->sequence,
                'step_name' => $d->approvalStep->name ?? "Step {$d->sequence}",
                'status' => $d->status->value ?? $d->status,
                'decided_by' => $d->decided_by_user_id ? ($deciders[$d->decided_by_user_id]->name ?? null) : null,
                'notes' => $d->notes,
                'decided_at' => $d->decided_at,
            ])->values();

            $currentDecision = $decisions->firstWhere('sequence', $approvalRequest->current_step_sequence);

            if ($currentDecision && $approvalRequest->status === LeaveApprovalRequestStatus::Pending) {
                $eligibleUserIds = $this->approverResolver->resolveApproverUserIds($currentDecision->approvalStep, $leaveRequest->employee);
                $canDecide = in_array($request->user()->id, $eligibleUserIds, true);
                $currentDecisionId = $currentDecision->id;

                $approverType = $currentDecision->approvalStep->approver_type->value ?? $currentDecision->approvalStep->approver_type;
                $waitingFor = match ($approverType) {
                    'direct_manager' => $leaveRequest->employee->manager
                        ? trim("{$leaveRequest->employee->manager->first_name} {$leaveRequest->employee->manager->last_name}")
                        : 'Manager',
                    'specific_employee' => $currentDecision->approvalStep->approverEmployee?->first_name ?? 'Employee tertentu',
                    'specific_role' => 'Role: '.($currentDecision->approvalStep->approverRole->name ?? '-'),
                    default => null,
                };
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'id' => $leaveRequest->id,
                'employee' => [
                    'id' => $leaveRequest->employee->id,
                    'name' => trim("{$leaveRequest->employee->first_name} {$leaveRequest->employee->last_name}"),
                    'photo_url' => $leaveRequest->employee->photo_url,
                    'department' => $leaveRequest->employee->department?->name,
                ],
                'leave_type' => $leaveRequest->leaveType,
                'status' => $leaveRequest->status->value ?? $leaveRequest->status,
                'start_date' => $leaveRequest->start_date->toDateString(),
                'end_date' => $leaveRequest->end_date->toDateString(),
                'is_half_day' => $leaveRequest->is_half_day,
                'total_days' => $leaveRequest->total_days,
                'reason' => $leaveRequest->reason,
                'attachment_url' => $leaveRequest->attachment_path ? Storage::disk('public')->url($leaveRequest->attachment_path) : null,
                'balance' => $leaveRequest->leaveBalance ? [
                    'initial_quota' => $leaveRequest->leaveBalance->initial_quota,
                    'used_days' => $leaveRequest->leaveBalance->used_days,
                    'remaining' => $leaveRequest->leaveBalance->remainingDaysAsString(),
                ] : null,
                'approval_history' => $history,
                'waiting_for' => $waitingFor,
                'can_decide' => $canDecide,
                'current_decision_id' => $currentDecisionId,
            ],
        ]);
    }

    /**
     * @return array{mode: string, employee_ids: array<int,int>}
     */
    private function resolveScope($user): array
    {
        if ($user->can('view leave requests')) {
            return ['mode' => 'all', 'employee_ids' => []];
        }

        $employee = $user->employee;

        if (! $employee) {
            return ['mode' => 'self', 'employee_ids' => []];
        }

        $subordinateIds = Employee::where('manager_employee_id', $employee->id)->pluck('id')->all();

        return [
            'mode' => count($subordinateIds) > 0 ? 'team' : 'self',
            'employee_ids' => array_merge([$employee->id], $subordinateIds),
        ];
    }
}
