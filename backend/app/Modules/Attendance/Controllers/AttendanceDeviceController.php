<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Requests\StoreAttendanceDeviceRequest;
use App\Modules\Attendance\Requests\UpdateAttendanceDeviceRequest;
use Illuminate\Support\Str;

class AttendanceDeviceController extends Controller
{
    public function index()
    {
        $devices = AttendanceDevice::with(['company', 'branch'])->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $devices,
        ]);
    }

    public function store(StoreAttendanceDeviceRequest $request)
    {
        $plainToken = Str::random(64);

        $device = AttendanceDevice::create([
            ...$request->validated(),
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance Device berhasil dibuat. Simpan token ini sekarang, tidak akan ditampilkan lagi.',
            'data' => [
                'device' => $device->load(['company', 'branch']),
                'token' => $plainToken,
            ],
        ], 201);
    }

    public function show(AttendanceDevice $attendanceDevice)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $attendanceDevice->load(['company', 'branch']),
        ]);
    }

    public function update(UpdateAttendanceDeviceRequest $request, AttendanceDevice $attendanceDevice)
    {
        $attendanceDevice->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Attendance Device berhasil diperbarui',
            'data' => $attendanceDevice->load(['company', 'branch']),
        ]);
    }

    public function regenerateToken(AttendanceDevice $attendanceDevice)
    {
        $plainToken = Str::random(64);

        $attendanceDevice->update(['token_hash' => hash('sha256', $plainToken)]);

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil di-generate ulang. Token lama langsung tidak berlaku.',
            'data' => ['token' => $plainToken],
        ]);
    }

    public function destroy(AttendanceDevice $attendanceDevice)
    {
        $attendanceDevice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance Device berhasil dihapus',
            'data' => null,
        ]);
    }
}