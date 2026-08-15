<?php

namespace App\Modules\EmployeeMovement\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Exception buat business-rule violation di Employee Movement (mis. tidak
 * ada Approval Flow yang berlaku). Sebelumnya tidak ada render() sama
 * sekali, jadi Laravel default handler nge-return 500 mentah tanpa pesan
 * yang jelas ke frontend — ini bug nyata, bukan intended behavior.
 */
class EmployeeMovementException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}
