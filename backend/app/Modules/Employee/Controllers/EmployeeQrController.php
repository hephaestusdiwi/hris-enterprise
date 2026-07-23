<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Str;

class EmployeeQrController extends Controller
{
    public function generate(Employee $employee)
    {
        $token = Str::random(48);

        $employee->update([
            'qr_token' => $token,
            'qr_generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR Code employee berhasil digenerate. QR lama langsung tidak berlaku.',
            'data' => [
                'qr_token' => $token,
                'qr_generated_at' => $employee->qr_generated_at,
            ],
        ]);
    }

    public function destroy(Employee $employee)
    {
        $employee->update([
            'qr_token' => null,
            'qr_generated_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR Code employee berhasil dicabut',
            'data' => null,
        ]);
    }
}