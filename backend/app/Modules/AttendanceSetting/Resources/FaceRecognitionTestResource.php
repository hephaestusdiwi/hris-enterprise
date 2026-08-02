<?php

namespace App\Modules\AttendanceSetting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaceRecognitionTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'is_live' => $this->resource['is_live'],
            'liveness_confidence' => $this->resource['liveness_confidence'],
            'is_match' => $this->resource['is_match'],
            'distance' => $this->resource['distance'],
            'threshold' => $this->resource['threshold'],
            'processing_time_ms' => $this->resource['processing_time_ms'],
            'message' => $this->resource['message'],
        ];
    }
}