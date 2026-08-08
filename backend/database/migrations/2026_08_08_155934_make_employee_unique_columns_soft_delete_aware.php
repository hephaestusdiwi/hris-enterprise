<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unique constraint employee_number & national_id_number sebelumnya adalah
 * plain unique index — tidak menghormati soft delete. Efeknya: employee yang
 * sudah di-soft-delete tetap "memakai" nomor/NIK-nya selamanya, employee baru
 * (termasuk rehire orang yang sama) dengan NIK yang sama akan ditolak di
 * level database, bukan cuma di level validasi Laravel.
 *
 * Diganti jadi PARTIAL unique index (`WHERE deleted_at IS NULL`) — didukung
 * baik oleh PostgreSQL (production) maupun SQLite (testing). Kombinasikan
 * dengan Rule::unique(...)->whereNull('deleted_at') di StoreEmployeeRequest/
 * UpdateEmployeeRequest supaya pesan error muncul rapi di level validasi,
 * BUKAN sebagai QueryException 500 dari constraint violation.
 */
return new class extends Migration
{
    private const INDEXES = [
        'employees_employee_number_unique' => 'employee_number',
        'employees_national_id_number_unique' => 'national_id_number',
    ];

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_number_unique');
            $table->dropUnique('employees_national_id_number_unique');
        });

        foreach (self::INDEXES as $indexName => $column) {
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON employees ({$column}) WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $indexName => $column) {
            DB::statement("DROP INDEX {$indexName}");
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('employee_number');
            $table->unique('national_id_number');
        });
    }
};