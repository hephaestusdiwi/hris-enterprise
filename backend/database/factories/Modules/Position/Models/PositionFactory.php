<?php

namespace Database\Factories\Modules\Position\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Position\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_position_id' => null,
            'name' => fake()->unique()->jobTitle(),
            'code' => strtoupper(fake()->unique()->lexify('POS???')),
            'is_active' => true,
        ];
    }
}