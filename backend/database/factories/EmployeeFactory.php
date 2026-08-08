<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('######'),
            'company_id' => CompanyFactory::new(),
            'user_id' => User::factory(),
            'join_date' => now()->subYear(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'national_id_number' => fake()->unique()->numerify('################'),
        ];
    }
}