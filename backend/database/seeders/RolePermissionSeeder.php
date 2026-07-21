<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view dashboard',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            'view companies',
            'create companies',
            'edit companies',
            'delete companies',
            'view branches',
            'create branches',
            'edit branches',
            'delete branches',
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view positions',
            'create positions',
            'edit positions',
            'delete positions',
            'view employment statuses',
            'create employment statuses',
            'edit employment statuses',
            'delete employment statuses',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'view holidays',
            'create holidays',
            'edit holidays',
            'delete holidays',
            'view job levels',
            'create job levels',
            'edit job levels',
            'delete job levels',
            'view shifts',
            'create shifts',
            'edit shifts',
            'delete shifts',
            'view working schedules',
            'create working schedules',
            'edit working schedules',
            'delete working schedules',
            'view attendance settings',
            'create attendance settings',
            'edit attendance settings',
            'delete attendance settings',
            'view approval flows',
            'create approval flows',
            'edit approval flows',
            'delete approval flows',
            'view attendances',
            'create attendances',
            'edit attendances',
            'delete attendances',
            'view attendance devices',
            'create attendance devices',
            'edit attendance devices',
            'delete attendance devices',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Wajib: reset cache registrar SEBELUM syncPermissions,
        // biar tidak baca daftar permission basi dari cache lama.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $hr = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);
        $hr->syncPermissions(['view dashboard', 'view users']);

        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employee->syncPermissions(['view dashboard']);
    }
}
