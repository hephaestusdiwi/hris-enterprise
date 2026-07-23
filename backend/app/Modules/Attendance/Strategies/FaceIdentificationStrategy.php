<?php

namespace App\Modules\Attendance\Strategies;

use App\Modules\Attendance\Contracts\AttendanceIdentificationStrategyInterface;
use App\Modules\Attendance\Exceptions\AttendanceValidationException;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Employee\Models\Employee;
use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Exceptions\FaceRecognitionException;

class FaceIdentificationStrategy implements AttendanceIdentificationStrategyInterface
{
    public function __construct(private FaceRecognitionServiceInterface $faceRecognitionService)
    {
    }

    public function identify(AttendanceDevice $device, array $payload): Employee
    {
        $imageBase64 = $payload['image_base64'] ?? null;

        if (! $imageBase64) {
            throw new AttendanceValidationException('image_base64 wajib diisi.');
        }

        try {
            $liveness = $this->faceRecognitionService->liveness($imageBase64);
        } catch (FaceRecognitionException $e) {
            throw new AttendanceValidationException($e->getMessage());
        }

        if (! $liveness['is_live']) {
            throw new AttendanceValidationException('Verifikasi wajah gagal: terindikasi bukan wajah asli (kemungkinan foto/spoofing).');
        }

        $candidateEmployees = Employee::where('company_id', $device->company_id)
            ->when($device->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->whereNotNull('face_embedding')
            ->get(['id', 'face_embedding']);

        if ($candidateEmployees->isEmpty()) {
            throw new AttendanceValidationException('Belum ada employee dengan wajah terdaftar di device ini.');
        }

        $candidates = $candidateEmployees->map(fn (Employee $employee) => [
            'employee_id' => $employee->id,
            'embedding' => $employee->face_embedding,
        ])->all();

        try {
            $recognition = $this->faceRecognitionService->recognize($imageBase64, $candidates);
        } catch (FaceRecognitionException $e) {
            throw new AttendanceValidationException($e->getMessage());
        }

        if (! $recognition['is_match']) {
            throw new AttendanceValidationException('Wajah tidak dikenali.');
        }

        $employee = Employee::find($recognition['employee_id']);

        if (! $employee) {
            throw new AttendanceValidationException('Employee hasil pengenalan wajah tidak ditemukan.');
        }

        return $employee;
    }
}