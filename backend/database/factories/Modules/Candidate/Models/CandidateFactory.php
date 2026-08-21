<?php

namespace Database\Factories\Modules\Candidate\Models;

use App\Modules\Candidate\Enums\CandidateSource;
use App\Modules\Candidate\Enums\CandidateStatus;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\JobVacancy\Models\JobVacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'job_vacancy_id' => JobVacancy::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => CandidateSource::CareerSite->value,
            'cv_path' => null,
            'status' => CandidateStatus::Applied->value,
            'score' => null,
            'notes' => null,
            'converted_employee_id' => null,
            'applied_at' => now(),
            'held_at' => null,
            'hired_at' => null,
            'rejected_at' => null,
            'reconsidered_from_candidate_id' => null,
        ];
    }
}
