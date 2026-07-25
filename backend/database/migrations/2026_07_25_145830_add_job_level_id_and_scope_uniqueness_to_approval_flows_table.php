<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_flows', function (Blueprint $table) {
            // Tier baru buat cascading resolver (Job Level), sejajar dengan
            // branch_id/department_id yang sudah ada sejak STEP 30.
            $table->foreignId('job_level_id')->nullable()->after('department_id')
                ->constrained()->nullOnDelete();
        });

        // Setiap ApprovalFlow merepresentasikan SATU dimensi scope saja
        // (job_level_id XOR department_id XOR branch_id XOR company-default polos),
        // konsisten dengan pola "satu subject per rule" ala Mekari Talenta.
        // 4 partial unique index di bawah mencegah duplikat ambigu di tiap tier —
        // pola yang sama persis dengan attendance_settings_company_default_unique
        // di STEP 29, cuma di-generalize jadi 4 tier alih-alih 2.

        DB::statement('
            CREATE UNIQUE INDEX approval_flows_company_default_unique
            ON approval_flows (company_id)
            WHERE branch_id IS NULL AND department_id IS NULL AND job_level_id IS NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX approval_flows_branch_scope_unique
            ON approval_flows (company_id, branch_id)
            WHERE branch_id IS NOT NULL AND department_id IS NULL AND job_level_id IS NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX approval_flows_department_scope_unique
            ON approval_flows (company_id, department_id)
            WHERE department_id IS NOT NULL AND branch_id IS NULL AND job_level_id IS NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX approval_flows_job_level_scope_unique
            ON approval_flows (company_id, job_level_id)
            WHERE job_level_id IS NOT NULL AND branch_id IS NULL AND department_id IS NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS approval_flows_job_level_scope_unique');
        DB::statement('DROP INDEX IF EXISTS approval_flows_department_scope_unique');
        DB::statement('DROP INDEX IF EXISTS approval_flows_branch_scope_unique');
        DB::statement('DROP INDEX IF EXISTS approval_flows_company_default_unique');

        Schema::table('approval_flows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_level_id');
        });
    }
};
