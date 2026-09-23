<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * aktivasi BPJS JP (Jaminan Pensiun). JP punya porsi karyawan
     * (1%) seperti JHT, jadi butuh cost bearer sendiri — mirror persis
     * kolom jht_cost_bearer yang sudah ada.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('employee_bpjs_participations', 'jp_cost_bearer')) {
            Schema::table('employee_bpjs_participations', function (Blueprint $table) {
                $table->string('jp_cost_bearer', 20)->default('default')->after('jht_cost_bearer');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_bpjs_participations', 'jp_cost_bearer')) {
            Schema::table('employee_bpjs_participations', function (Blueprint $table) {
                $table->dropColumn('jp_cost_bearer');
            });
        }
    }
};