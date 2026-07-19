<?php

namespace App\Modules\JobLevel\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\JobLevel\Models\JobLevel;
use App\Modules\JobLevel\Requests\StoreJobLevelRequest;
use App\Modules\JobLevel\Requests\UpdateJobLevelRequest;

class JobLevelController extends Controller
{
    public function index()
    {
        $jobLevels = JobLevel::with('company')->orderBy('level_order')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $jobLevels,
        ]);
    }

    public function store(StoreJobLevelRequest $request)
    {
        $jobLevel = JobLevel::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job level berhasil dibuat',
            'data' => $jobLevel->load('company'),
        ], 201);
    }

    public function show(JobLevel $jobLevel)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $jobLevel->load('company'),
        ]);
    }

    public function update(UpdateJobLevelRequest $request, JobLevel $jobLevel)
    {
        $jobLevel->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job level berhasil diperbarui',
            'data' => $jobLevel->load('company'),
        ]);
    }

    public function destroy(JobLevel $jobLevel)
    {
        $jobLevel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job level berhasil dihapus',
            'data' => null,
        ]);
    }
}