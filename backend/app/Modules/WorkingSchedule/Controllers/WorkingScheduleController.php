<?php

namespace App\Modules\WorkingSchedule\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WorkingSchedule\Models\WorkingSchedule;
use App\Modules\WorkingSchedule\Requests\StoreWorkingScheduleRequest;
use App\Modules\WorkingSchedule\Requests\UpdateWorkingScheduleRequest;
use Illuminate\Support\Facades\DB;

class WorkingScheduleController extends Controller
{
    protected array $relations = ['company', 'details.shift'];

    public function index()
    {
        $schedules = WorkingSchedule::with($this->relations)->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $schedules,
        ]);
    }

    public function store(StoreWorkingScheduleRequest $request)
    {
        $validated = $request->validated();
        $details = $validated['details'];
        unset($validated['details']);

        $schedule = DB::transaction(function () use ($validated, $details) {
            $schedule = WorkingSchedule::create($validated);
            $schedule->details()->createMany($details);

            return $schedule;
        });

        return response()->json([
            'success' => true,
            'message' => 'Working schedule berhasil dibuat',
            'data' => $schedule->load($this->relations),
        ], 201);
    }

    public function show(WorkingSchedule $workingSchedule)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $workingSchedule->load($this->relations),
        ]);
    }

    public function update(UpdateWorkingScheduleRequest $request, WorkingSchedule $workingSchedule)
    {
        $validated = $request->validated();
        $details = $validated['details'];
        unset($validated['details']);

        DB::transaction(function () use ($workingSchedule, $validated, $details) {
            $workingSchedule->update($validated);
            $workingSchedule->details()->delete();
            $workingSchedule->details()->createMany($details);
        });

        return response()->json([
            'success' => true,
            'message' => 'Working schedule berhasil diperbarui',
            'data' => $workingSchedule->load($this->relations),
        ]);
    }

    public function destroy(WorkingSchedule $workingSchedule)
    {
        $workingSchedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Working schedule berhasil dihapus',
            'data' => null,
        ]);
    }
}