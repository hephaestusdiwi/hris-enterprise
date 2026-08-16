<?php

namespace Database\Factories;

use App\Modules\Announcement\Models\AnnouncementCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementCategoryFactory extends Factory
{
    protected $model = AnnouncementCategory::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('CAT????'));

        return [
            'name' => ucfirst(strtolower($code)),
            'code' => $code,
            'is_active' => true,
        ];
    }
}
