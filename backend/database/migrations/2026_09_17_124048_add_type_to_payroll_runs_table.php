<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 7 — THR & Non-Regular Payroll. Migration INCREMENTAL, tidak
     * menyentuh migration create_payroll_runs_table lama sama sekali.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_runs', 'type')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                // Default 'regular' — seluruh payroll run existing otomatis
                // dianggap Regular Payroll, behavior/data existing tidak berubah.
                $table->string('type', 20)->default('regular')->after('company_id');
            });
        }

        // Unique constraint lama (company_id, period_year, period_month) tidak
        // cukup lagi: company sekarang bisa punya lebih dari 1 run per periode
        // (Regular + THR + Non-Regular bisa jalan bersamaan di bulan yang
        // sama). Data existing aman — seluruhnya sudah type='regular' yang
        // sama, jadi drop+recreate index ini tidak menghasilkan duplikat baru.
        $indexes = Schema::getIndexes('payroll_runs');
        $hasOldUnique = collect($indexes)->contains(fn ($idx) => $idx['name'] === 'payroll_runs_company_id_period_year_period_month_unique');
        $hasNewUnique = collect($indexes)->contains(fn ($idx) => $idx['name'] === 'payroll_runs_company_id_period_year_period_month_type_unique');

        if ($hasOldUnique && ! $hasNewUnique) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropUnique('payroll_runs_company_id_period_year_period_month_unique');
            });
        }

        if (! $hasNewUnique) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->unique(['company_id', 'period_year', 'period_month', 'type']);
            });
        }
    }

    public function down(): void
    {
        $indexes = Schema::getIndexes('payroll_runs');
        $hasNewUnique = collect($indexes)->contains(fn ($idx) => $idx['name'] === 'payroll_runs_company_id_period_year_period_month_type_unique');

        if ($hasNewUnique) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropUnique('payroll_runs_company_id_period_year_period_month_type_unique');
                $table->unique(['company_id', 'period_year', 'period_month']);
            });
        }

        if (Schema::hasColumn('payroll_runs', 'type')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};