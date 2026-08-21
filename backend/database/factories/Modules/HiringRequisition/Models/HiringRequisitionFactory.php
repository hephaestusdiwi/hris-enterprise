<?php

namespace Database\Factories\Modules\HiringRequisition\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\HiringRequisition\Models\HiringRequisition;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HiringRequisition>
 */
class HiringRequisitionFactory extends Factory
{
    protected $model = HiringRequisition::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => null,
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'requested_by_employee_id' => Employee::factory(),
            'replacement_for_employee_id' => null,
            'reason' => 'new_position',
            'employment_type' => fake()->randomElement(['Full-Time', 'Part-Time', 'Contract', 'Internship']),
            'headcount_requested' => fake()->numberBetween(1, 5),
            'headcount_filled' => 0,
            'target_start_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'justification' => fake()->sentence(),
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}