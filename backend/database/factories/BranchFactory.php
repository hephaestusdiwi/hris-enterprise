<?php

namespace Database\Factories;

use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->lexify('BR???')),
            'is_active' => true,
        ];
    }
}
