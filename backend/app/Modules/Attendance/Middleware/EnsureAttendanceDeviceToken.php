<?php

namespace App\Modules\Attendance\Middleware;

use App\Modules\Attendance\Models\AttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Device token tidak ditemukan.',
                'data' => null,
            ], 401);
        }

        $device = AttendanceDevice::where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device token tidak valid atau device nonaktif.',
                'data' => null,
            ], 401);
        }

        $device->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}