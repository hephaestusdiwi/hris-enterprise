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
            'view employment types',
            'create employment types',
            'edit employment types',
            'delete employment types',
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
            'view leave types',
            'create leave types',
            'edit leave types',
            'delete leave types',
            'view leave balances',
            'view leave requests',
            'view leave types',
            'view salary components',
            'create salary components',
            'edit salary components',
            'delete salary components',
            'view salary structures',
            'edit salary structures',
            'delete salary structures',
            'view employee salaries',
            'create employee salaries',
            'delete employee salaries',
            'view employee allowances',
            'create employee allowances',
            'delete employee allowances',
            'view employee deductions',
            'create employee deductions',
            'edit employee deductions',
            'view employee deductions',
            'edit employee deductions',
            'create employee deductions',
            'view loans',
            'create loans',
            'edit loans',
            'disburse loans',
            'cancel loans',
            'view bpjs settings',
            'create bpjs settings',
            'edit bpjs settings',
            'delete bpjs settings',
            'view tax settings',
            'create tax settings',
            'edit tax settings',
            'delete tax settings',
            'view hiring requisitions',
            'create hiring requisitions',
            'edit hiring requisitions',
            'cancel hiring requisitions',
            'create job vacancies',
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
        $hr->syncPermissions([
            'view dashboard',
            'view users',
            // Employee core: HR bisa kelola data karyawan sehari-hari.
            // 'delete employees' SENGAJA tidak dikasih dulu — hapus employee
            // itu aksi destruktif yang seharusnya lewat flow Offboarding
            // (Phase 2 Employee Lifecycle), bukan hard delete langsung.
            'view employees',
            'create employees',
            'edit employees',
            'view hiring requisitions',
            'create hiring requisitions',
            'edit hiring requisitions',
            'cancel hiring requisitions',
            'create job vacancies',
        ]);

        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'view dashboard',
            'create hiring requisitions',
        ]);
    }
}