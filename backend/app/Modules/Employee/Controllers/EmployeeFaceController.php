<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Exceptions\FaceRecognitionException;
use Illuminate\Http\Request;

class EmployeeFaceController extends Controller
{
    public function __construct(private FaceRecognitionServiceInterface $faceRecognitionService)
    {
    }

    public function enroll(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'image_base64' => ['required', 'string'],
        ]);

        try {
            $result = $this->faceRecognitionService->encode($validated['image_base64']);
        } catch (FaceRecognitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $employee->update([
            'face_embedding' => $result['embedding'],
            'face_embedding_model' => $result['model'],
            'face_registered_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Wajah employee berhasil didaftarkan',
            'data' => [
                'employee_id' => $employee->id,
                'face_registered_at' => $employee->face_registered_at,
            ],
        ]);
    }

    public function destroyEnrollment(Employee $employee)
    {
        $employee->update([
            'face_embedding' => null,
            'face_embedding_model' => null,
            'face_registered_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment wajah employee berhasil dihapus',
            'data' => null,
        ]);
    }
}