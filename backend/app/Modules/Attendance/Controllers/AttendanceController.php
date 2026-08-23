<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Enums\AttendanceActivityType;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Requests\StoreAttendanceRequest;
use App\Modules\Attendance\Requests\UpdateAttendanceRequest;
use App\Modules\Attendance\Services\AttendanceActivityService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceActivityService $activityService)
    {
    }

    public function index(Request $request)
    {
        $attendances = Attendance::with(['employee', 'shift'])
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('date_from'), fn ($query, $dateFrom) => $query->where('attendance_date', '>=', $dateFrom))
            ->when($request->query('date_to'), fn ($query, $dateTo) => $query->where('attendance_date', '<=', $dateTo))
            ->latest('attendance_date')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $attendances,
        ]);
    }

    public function store(StoreAttendanceRequest $request)
    {
        $attendance = Attendance::create($request->validated());

        $this->activityService->record(
            employeeId: $attendance->employee_id,
            type: AttendanceActivityType::AttendanceCreated,
            attendanceId: $attendance->id,
            actorUserId: $request->user()?->id,
            metadata: ['attendance_date' => $attendance->attendance_date->toDateString(), 'status' => $attendance->status->value],
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance berhasil dibuat',
            'data' => $attendance->load(['employee', 'shift']),
        ], 201);
    }

    public function show(Attendance $attendance)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $attendance->load(['employee', 'shift']),
        ]);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $original = $attendance->only(['shift_id', 'clock_in', 'clock_out', 'status', 'notes']);

        $attendance->update($request->validated());

        $changes = [];
        foreach ($original as $field => $oldValue) {
            $newValue = $attendance->{$field};
            if ($oldValue != $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (! empty($changes)) {
            $this->activityService->record(
                employeeId: $attendance->employee_id,
                type: AttendanceActivityType::AttendanceCorrected,
                attendanceId: $attendance->id,
                actorUserId: $request->user()?->id,
                metadata: ['changes' => $changes],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance berhasil diperbarui',
            'data' => $attendance->load(['employee', 'shift']),
        ]);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance berhasil dihapus',
            'data' => null,
        ]);
    }
}