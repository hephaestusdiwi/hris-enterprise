<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalisasi data lama yang punya kombinasi scope (misal branch_id +
        // department_id keisi bareng — legal sebelum migration ini, tidak lagi
        // setelahnya). Prioritas dipertahankan sama seperti urutan cascade
        // resolver: job_level_id > department_id > branch_id.
        DB::statement('
            UPDATE approval_flows
            SET branch_id = NULL
            WHERE job_level_id IS NOT NULL AND branch_id IS NOT NULL
        ');

        DB::statement('
            UPDATE approval_flows
            SET department_id = NULL
            WHERE job_level_id IS NOT NULL AND department_id IS NOT NULL
        ');

        DB::statement('
            UPDATE approval_flows
            SET branch_id = NULL
            WHERE department_id IS NOT NULL AND branch_id IS NOT NULL
        ');

        // CHECK constraint: maksimal SATU dari (branch_id, department_id,
        // job_level_id) yang boleh terisi per row. company-default row
        // (semua NULL) tetap diperbolehkan (0 dari 3 terisi).
        DB::statement('
            ALTER TABLE approval_flows
            ADD CONSTRAINT approval_flows_single_scope_check
            CHECK (
                (CASE WHEN branch_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN department_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN job_level_id IS NOT NULL THEN 1 ELSE 0 END) <= 1
            )
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE approval_flows DROP CONSTRAINT IF EXISTS approval_flows_single_scope_check');
    }
};
