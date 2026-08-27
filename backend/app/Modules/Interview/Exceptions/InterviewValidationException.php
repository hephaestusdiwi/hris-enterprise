<?php

namespace App\Modules\Interview\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class InterviewValidationException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}