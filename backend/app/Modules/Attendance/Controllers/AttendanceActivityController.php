<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceActivity;
use Illuminate\Http\Request;

class AttendanceActivityController extends Controller
{
    public function index(Request $request)
    {
        $activities = AttendanceActivity::with(['employee', 'actor'])
            ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($request->query('activity_type'), fn ($query, $activityType) => $query->where('activity_type', $activityType))
            ->when($request->query('date_from'), fn ($query, $dateFrom) => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($request->query('date_to'), fn ($query, $dateTo) => $query->whereDate('occurred_at', '<=', $dateTo))
            ->latest('occurred_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $activities,
        ]);
    }

    public function show(AttendanceActivity $activity)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $activity->load(['employee', 'actor', 'attendance']),
        ]);
    }
}