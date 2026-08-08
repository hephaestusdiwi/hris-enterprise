<?php

namespace Database\Factories;

use App\Modules\EmploymentType\Models\EmploymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentTypeFactory extends Factory
{
    protected $model = EmploymentType::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('TYPE????'));

        return [
            'name' => ucfirst(strtolower($code)),
            'code' => $code,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}