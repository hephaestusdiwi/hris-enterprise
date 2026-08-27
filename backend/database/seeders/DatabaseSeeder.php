<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use App\Modules\EmploymentType\Models\EmploymentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@hris.local'],
            ['name' => 'Admin HRIS', 'password' => bcrypt('password123')]
        );
        $admin->assignRole('admin');

        $types = [
            ['name' => 'Permanent', 'code' => 'PERMANENT'],
            ['name' => 'Contract', 'code' => 'CONTRACT'],
            ['name' => 'Intern', 'code' => 'INTERN'],
            ['name' => 'Freelance', 'code' => 'FREELANCE'],
            ['name' => 'Outsource', 'code' => 'OUTSOURCE'],
        ];

        foreach ($types as $type) {
            EmploymentType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        $statuses = [
            ['name' => 'Active', 'code' => 'ACTIVE'],
            ['name' => 'Inactive', 'code' => 'INACTIVE'],
            ['name' => 'Resigned', 'code' => 'RESIGNED'],
            ['name' => 'Terminated', 'code' => 'TERMINATED'],
            ['name' => 'Retired', 'code' => 'RETIRED'],
            ['name' => 'Suspended', 'code' => 'SUSPENDED'],
        ];

        foreach ($statuses as $status) {
            EmploymentStatus::firstOrCreate(
                ['code' => $status['code']],
                $status
            );
        }

        $interviewStages = [
            ['name' => 'HRD Interview', 'code' => 'HRD', 'sequence' => 1],
            ['name' => 'User Interview', 'code' => 'USER', 'sequence' => 2],
            ['name' => 'Final Interview', 'code' => 'FINAL', 'sequence' => 3],
        ];

        foreach ($interviewStages as $stage) {
            \App\Modules\Interview\Models\InterviewStage::firstOrCreate(
                ['code' => $stage['code']],
                [...$stage, 'is_active' => true],
            );
        }
    }
}