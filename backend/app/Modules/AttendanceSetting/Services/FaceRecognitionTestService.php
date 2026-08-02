<?php

namespace App\Modules\AttendanceSetting\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Exceptions\FaceRecognitionException;
use Illuminate\Http\Client\ConnectionException;

class FaceRecognitionTestService
{
    public function __construct(
        private FaceRecognitionServiceInterface $faceRecognitionService,
    ) {
    }

    /**
     * Uji liveness + recognition employee terhadap face_embedding miliknya sendiri.
     * Murni diagnostik — TIDAK menyentuh tabel attendances sama sekali.
     */
    public function test(int $employeeId, string $imageBase64): array
    {
        $start = microtime(true);

        $employee = Employee::find($employeeId);

        if (! $employee) {
            throw new FaceRecognitionException('Employee tidak ditemukan.');
        }

        if (! $employee->face_embedding) {
            throw new FaceRecognitionException('Employee ini belum mendaftarkan wajah. Gunakan menu Daftarkan Wajah terlebih dahulu.');
        }

        $imageBase64 = $this->stripDataUriPrefix($imageBase64);

        $liveness = $this->callService(fn () => $this->faceRecognitionService->liveness($imageBase64));

        $isLive = (bool) ($liveness['is_live'] ?? false);

        if (! $isLive) {
            return $this->diagnosticResult($start, isLive: false, liveness: $liveness, message: $liveness['message'] ?? 'Verifikasi liveness gagal, kemungkinan bukan wajah asli (foto/spoofing).');
        }

        $recognition = $this->callService(fn () => $this->faceRecognitionService->recognize($imageBase64, [[
            'employee_id' => $employee->id,
            'embedding' => $employee->face_embedding,
        ]]));

        $isMatch = (bool) ($recognition['is_match'] ?? false);

        return [
            'is_live' => true,
            'liveness_confidence' => $liveness['confidence'] ?? null,
            'is_match' => $isMatch,
            'distance' => $recognition['distance'] ?? null,
            'threshold' => $recognition['threshold'] ?? null,
            'processing_time_ms' => $this->elapsedMs($start),
            'message' => $isMatch
                ? 'Wajah cocok dengan data yang terdaftar.'
                : ($recognition['message'] ?? 'Wajah tidak cocok dengan data yang terdaftar (di atas threshold jarak).'),
        ];
    }

    private function diagnosticResult(float $start, bool $isLive, array $liveness, string $message): array
    {
        return [
            'is_live' => $isLive,
            'liveness_confidence' => $liveness['confidence'] ?? null,
            'is_match' => false,
            'distance' => null,
            'threshold' => null,
            'processing_time_ms' => $this->elapsedMs($start),
            'message' => $message,
        ];
    }

    private function callService(callable $fn): array
    {
        try {
            return $fn();
        } catch (FaceRecognitionException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            throw new FaceRecognitionException('Face Recognition Service tidak dapat dihubungi (kemungkinan down atau timeout). Coba lagi beberapa saat.');
        }
    }

    private function stripDataUriPrefix(string $imageBase64): string
    {
        return preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64) ?? $imageBase64;
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}