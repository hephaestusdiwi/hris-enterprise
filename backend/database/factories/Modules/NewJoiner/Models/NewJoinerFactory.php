<?php

namespace Database\Factories\Modules\NewJoiner\Models;

use App\Modules\Candidate\Models\Candidate;
use App\Modules\NewJoiner\Models\NewJoiner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewJoiner>
 */
class NewJoinerFactory extends Factory
{
    protected $model = NewJoiner::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'token' => Str::random(48),
            'status' => 'sent',
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->date(),
            'marital_status' => fake()->randomElement(['single', 'married']),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'national_id_number' => fake()->numerify('################'),
            'tax_number' => fake()->numerify('##.###.###.#-###.###'),
            'bank_name' => 'BCA',
            'bank_account_number' => fake()->numerify('##########'),
            'bank_account_holder_name' => fake()->name(),
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }
}