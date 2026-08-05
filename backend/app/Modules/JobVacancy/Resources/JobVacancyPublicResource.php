<?php

namespace App\Modules\JobVacancy\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobVacancyPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'employment_type' => $this->employmentType?->name,
            'company' => $this->company?->name,
            'branch' => $this->branch?->name,
            'department' => $this->department?->name,
            'application_deadline' => $this->application_deadline?->toDateString(),
            'published_at' => $this->published_at?->toIso8601String(),
            'application_method' => $this->application_method?->value,
            'external_apply_url' => $this->external_apply_url,
        ];
    }
}