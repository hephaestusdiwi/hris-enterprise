<?php

namespace App\Modules\Screening\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ScreeningValidationException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}