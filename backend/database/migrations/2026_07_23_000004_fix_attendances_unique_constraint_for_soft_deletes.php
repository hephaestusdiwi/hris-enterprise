<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendances DROP CONSTRAINT attendances_employee_id_attendance_date_unique');
        DB::statement('CREATE UNIQUE INDEX attendances_employee_id_attendance_date_unique ON attendances (employee_id, attendance_date) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX attendances_employee_id_attendance_date_unique');
        DB::statement('ALTER TABLE attendances ADD CONSTRAINT attendances_employee_id_attendance_date_unique UNIQUE (employee_id, attendance_date)');
    }
};