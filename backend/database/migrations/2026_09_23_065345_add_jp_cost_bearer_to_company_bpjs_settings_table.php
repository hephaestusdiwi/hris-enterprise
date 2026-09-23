<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_bpjs_settings', 'default_jp_cost_bearer')) {
            Schema::table('company_bpjs_settings', function (Blueprint $table) {
                $table->string('default_jp_cost_bearer', 20)->default('employee_borne')->after('default_jht_cost_bearer');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_bpjs_settings', 'default_jp_cost_bearer')) {
            Schema::table('company_bpjs_settings', function (Blueprint $table) {
                $table->dropColumn('default_jp_cost_bearer');
            });
        }
    }
};