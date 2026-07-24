<?php

namespace App\Modules\WorkingSchedule\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WorkingSchedule\Models\WorkingScheduleAssignment;
use App\Modules\WorkingSchedule\Requests\StoreWorkingScheduleAssignmentRequest;
use App\Modules\WorkingSchedule\Requests\UpdateWorkingScheduleAssignmentRequest;

class WorkingScheduleAssignmentController extends Controller
{
    public function index()
    {
        $assignments = WorkingScheduleAssignment::with(['workingSchedule', 'target'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $assignments,
        ]);
    }

    public function store(StoreWorkingScheduleAssignmentRequest $request)
    {
        $assignment = WorkingScheduleAssignment::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Working Schedule Assignment berhasil dibuat',
            'data' => $assignment->load(['workingSchedule', 'target']),
        ], 201);
    }

    public function show(WorkingScheduleAssignment $workingScheduleAssignment)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $workingScheduleAssignment->load(['workingSchedule', 'target']),
        ]);
    }

    public function update(UpdateWorkingScheduleAssignmentRequest $request, WorkingScheduleAssignment $workingScheduleAssignment)
    {
        $workingScheduleAssignment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Working Schedule Assignment berhasil diperbarui',
            'data' => $workingScheduleAssignment->load(['workingSchedule', 'target']),
        ]);
    }

    public function destroy(WorkingScheduleAssignment $workingScheduleAssignment)
    {
        $workingScheduleAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Working Schedule Assignment berhasil dihapus',
            'data' => null,
        ]);
    }
}