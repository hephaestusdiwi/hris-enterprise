<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceDevice;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class OfficeQrTokenService
{
    private const TTL_SECONDS = 30;
    private const KEY_PREFIX = 'attendance:office-qr:';

    /**
     * @return array{token: string, expires_in: int}
     */
    public function generate(AttendanceDevice $device): array
    {
        $token = Str::random(40);

        Redis::setex(self::KEY_PREFIX.$token, self::TTL_SECONDS, $device->id);

        return [
            'token' => $token,
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    public function resolveDeviceId(string $token): ?int
    {
        $deviceId = Redis::get(self::KEY_PREFIX.$token);

        return $deviceId !== null ? (int) $deviceId : null;
    }
}