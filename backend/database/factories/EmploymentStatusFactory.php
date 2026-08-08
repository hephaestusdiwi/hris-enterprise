<?php

namespace Database\Factories;

use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentStatusFactory extends Factory
{
    protected $model = EmploymentStatus::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('STATUS????'));

        return [
            'name' => ucfirst(strtolower($code)),
            'code' => $code,
            'is_active' => true,
        ];
    }
}