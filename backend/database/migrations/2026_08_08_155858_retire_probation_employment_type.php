<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Employment Type != Probation.
 *
 * Employment Type = jenis hubungan kerja (Permanent/Contract/Intern/dst).
 * Probation = fase employment berdasarkan `employees.probation_end_date`,
 * independen dari Employment Type apapun.
 *
 * Migration ini TIDAK menghapus row 'Probation' dari employment_types (masih
 * ada FK history yang mungkin mereferensikannya di masa lalu, dan tabel ini
 * sudah punya kolom `is_active` untuk kasus persis seperti ini) — cuma
 * di-retire (is_active = false) supaya tidak muncul lagi sebagai pilihan baru.
 *
 * Employee yang employment_type_id-nya masih mengarah ke 'Probation' DI-NULL-KAN,
 * bukan ditebak jadi Permanent/Contract — kita tidak tahu jenis hubungan kerja
 * aslinya dari data yang ada, menebak salah lebih berbahaya daripada kosong.
 * Employee ID yang terdampak dicatat ke log supaya HR bisa review manual.
 */
return new class extends Migration
{
    private const CODE = 'PROBATION';

    public function up(): void
    {
        $probationTypeId = DB::table('employment_types')->where('code', self::CODE)->value('id');

        if (! $probationTypeId) {
            // Fresh install: DatabaseSeeder sudah tidak pernah membuat row ini.
            return;
        }

        $affectedEmployeeIds = DB::table('employees')
            ->where('employment_type_id', $probationTypeId)
            ->pluck('id');

        if ($affectedEmployeeIds->isNotEmpty()) {
            DB::table('employees')
                ->where('employment_type_id', $probationTypeId)
                ->update(['employment_type_id' => null]);

            Log::warning('[retire_probation_employment_type] employment_type_id di-null-kan, perlu review manual HR untuk set jenis hubungan kerja yang benar.', [
                'employee_ids' => $affectedEmployeeIds->all(),
            ]);
        }

        DB::table('employment_types')
            ->where('id', $probationTypeId)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Best-effort: aktifkan lagi row-nya. Referensi employee yang sudah
        // di-null-kan di up() TIDAK bisa dikembalikan (informasi aslinya
        // sudah tidak ada di database sejak jauh sebelum migration ini).
        DB::table('employment_types')
            ->where('code', self::CODE)
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};