<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // NOT NULL — semua baris sudah dipastikan terisi lewat
        // `php artisan employees:backfill-user-accounts` sebelum migration ini jalan.
        DB::statement('ALTER TABLE employees ALTER COLUMN user_id SET NOT NULL');

        // Partial unique index (bukan UNIQUE polos) — konsisten dengan pola
        // attendance_settings (STEP 29). Alasan: employees pakai soft delete,
        // jadi User yang employee-nya sudah di-resign (soft-deleted) tetap
        // bisa dipakai ulang untuk Employee baru tanpa nabrak constraint mati.
        DB::statement('
            CREATE UNIQUE INDEX employees_user_id_unique_active
            ON employees (user_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS employees_user_id_unique_active');
        DB::statement('ALTER TABLE employees ALTER COLUMN user_id DROP NOT NULL');
    }
};
