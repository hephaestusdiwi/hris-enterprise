<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
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

        $statuses = [
            ['name' => 'Permanent', 'code' => 'PERMANENT'],
            ['name' => 'Contract', 'code' => 'CONTRACT'],
            ['name' => 'Probation', 'code' => 'PROBATION'],
            ['name' => 'Intern', 'code' => 'INTERN'],
        ];

        foreach ($statuses as $status) {
            EmploymentStatus::firstOrCreate(['code' => $status['code']], $status);
        }
    }
}
