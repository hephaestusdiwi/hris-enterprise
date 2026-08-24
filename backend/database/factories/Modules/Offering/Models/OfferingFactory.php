<?php

namespace Database\Factories\Modules\Offering\Models;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\Offering\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    protected $model = Offering::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'proposed_start_date' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'proposed_salary' => fake()->numberBetween(5000000, 20000000),
            'compensation_notes' => null,
            'notes' => null,
            'status' => 'draft',
        ];
    }
}