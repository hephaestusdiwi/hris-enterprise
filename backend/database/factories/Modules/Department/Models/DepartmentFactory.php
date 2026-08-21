<?php

namespace Database\Factories\Modules\Department\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('DEPT???')),
            'is_active' => true,
        ];
    }
}