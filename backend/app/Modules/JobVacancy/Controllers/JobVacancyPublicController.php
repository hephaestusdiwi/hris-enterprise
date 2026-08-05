<?php

namespace App\Modules\JobVacancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Enums\VacancyVisibility;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\JobVacancy\Resources\JobVacancyPublicResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobVacancyPublicController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $vacancies = JobVacancy::query()
            ->where('status', JobVacancyStatus::Published->value)
            ->whereIn('visibility', [VacancyVisibility::External->value, VacancyVisibility::Both->value])
            ->with(['employmentType', 'company', 'branch', 'department'])
            ->latest('published_at')
            ->paginate();

        return JobVacancyPublicResource::collection($vacancies);
    }

    public function show(string $slug): JobVacancyPublicResource
    {
        $vacancy = JobVacancy::query()
            ->where('slug', $slug)
            ->where('status', JobVacancyStatus::Published->value)
            ->whereIn('visibility', [VacancyVisibility::External->value, VacancyVisibility::Both->value])
            ->with(['employmentType', 'company', 'branch', 'department'])
            ->firstOrFail();

        return new JobVacancyPublicResource($vacancy);
    }
}