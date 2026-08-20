<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * 1. Tambahkan approval_type sementara nullable karena
         *    data existing belum memiliki nilai ini.
         */
        Schema::table('approval_flows', function (Blueprint $table) {
            $table->string('approval_type', 50)
                ->nullable()
                ->after('code');
        });

        /*
         * 2. Existing flow yang saat ini sudah dibuat adalah
         *    Hiring Requisition.
         */
        DB::table('approval_flows')
            ->whereNull('approval_type')
            ->update([
                'approval_type' => 'hiring_requisition',
            ]);

        /*
         * 3. Setelah seluruh existing row terisi,
         *    jadikan kolom wajib.
         */
        DB::statement('
            ALTER TABLE approval_flows
            ALTER COLUMN approval_type SET NOT NULL
        ');

        /*
         * 4. Hapus uniqueness lama yang hanya melihat scope.
         */
        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_company_default_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_branch_scope_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_department_scope_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_job_level_scope_unique'
        );

        /*
         * 5. Scope sekarang unik PER JENIS APPROVAL.
         */

        // Company-wide:
        // satu flow untuk satu company + satu approval type.
        DB::statement('
            CREATE UNIQUE INDEX approval_flows_company_default_unique
            ON approval_flows (company_id, approval_type)
            WHERE branch_id IS NULL
              AND department_id IS NULL
              AND job_level_id IS NULL
              AND deleted_at IS NULL
        ');

        // Branch:
        DB::statement('
            CREATE UNIQUE INDEX approval_flows_branch_scope_unique
            ON approval_flows (company_id, approval_type, branch_id)
            WHERE branch_id IS NOT NULL
              AND department_id IS NULL
              AND job_level_id IS NULL
              AND deleted_at IS NULL
        ');

        // Department:
        DB::statement('
            CREATE UNIQUE INDEX approval_flows_department_scope_unique
            ON approval_flows (company_id, approval_type, department_id)
            WHERE department_id IS NOT NULL
              AND branch_id IS NULL
              AND job_level_id IS NULL
              AND deleted_at IS NULL
        ');

        // Job Level:
        DB::statement('
            CREATE UNIQUE INDEX approval_flows_job_level_scope_unique
            ON approval_flows (company_id, approval_type, job_level_id)
            WHERE job_level_id IS NOT NULL
              AND branch_id IS NULL
              AND department_id IS NULL
              AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_job_level_scope_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_department_scope_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_branch_scope_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS approval_flows_company_default_unique'
        );

        Schema::table('approval_flows', function (Blueprint $table) {
            $table->dropColumn('approval_type');
        });
    }
};