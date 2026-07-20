<?php

namespace App\Modules\AttendanceSetting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AttendanceSetting\Models\AttendanceSetting;
use App\Modules\AttendanceSetting\Requests\StoreAttendanceSettingRequest;
use App\Modules\AttendanceSetting\Requests\UpdateAttendanceSettingRequest;

class AttendanceSettingController extends Controller
{
    public function index()
    {
        $settings = AttendanceSetting::with(['company', 'branch'])->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $settings,
        ]);
    }

    public function store(StoreAttendanceSettingRequest $request)
    {
        try {
            $setting = AttendanceSetting::create($request->validated());
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Attendance setting untuk kombinasi company/branch ini sudah ada.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance setting berhasil dibuat',
            'data' => $setting->load(['company', 'branch']),
        ], 201);
    }

    public function show(AttendanceSetting $attendanceSetting)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $attendanceSetting->load(['company', 'branch']),
        ]);
    }

    public function update(UpdateAttendanceSettingRequest $request, AttendanceSetting $attendanceSetting)
    {
        try {
            $attendanceSetting->update($request->validated());
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Attendance setting untuk kombinasi company/branch ini sudah ada.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance setting berhasil diperbarui',
            'data' => $attendanceSetting->load(['company', 'branch']),
        ]);
    }

    public function destroy(AttendanceSetting $attendanceSetting)
    {
        $attendanceSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance setting berhasil dihapus',
            'data' => null,
        ]);
    }
}