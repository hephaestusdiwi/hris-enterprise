<?php

namespace App\Modules\FaceRecognition\Contracts;

interface FaceRecognitionServiceInterface
{
    public function encode(string $imageBase64): array;

    public function liveness(string $imageBase64): array;

    /**
     * @param array<int, array{employee_id: int, embedding: array<int, float>}> $candidates
     */
    public function recognize(string $imageBase64, array $candidates): array;
}