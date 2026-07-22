<?php

namespace App\Modules\FaceRecognition\Services;

use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Exceptions\FaceRecognitionException;
use Illuminate\Support\Facades\Http;

class HttpFaceRecognitionService implements FaceRecognitionServiceInterface
{
    public function encode(string $imageBase64): array
    {
        return $this->post('/encode', ['image_base64' => $imageBase64]);
    }

    public function liveness(string $imageBase64): array
    {
        return $this->post('/liveness', ['image_base64' => $imageBase64]);
    }

    public function recognize(string $imageBase64, array $candidates): array
    {
        return $this->post('/recognize', [
            'image_base64' => $imageBase64,
            'candidates' => $candidates,
        ]);
    }

    private function post(string $path, array $payload): array
    {
        $response = Http::baseUrl(config('face_recognition.base_url'))
            ->withHeaders(['X-Api-Key' => config('face_recognition.api_key')])
            ->timeout((int) config('face_recognition.timeout'))
            ->post($path, $payload);

        if ($response->failed()) {
            throw new FaceRecognitionException(
                $response->json('detail') ?? 'Face Recognition Service gagal memproses permintaan.'
            );
        }

        return $response->json();
    }
}