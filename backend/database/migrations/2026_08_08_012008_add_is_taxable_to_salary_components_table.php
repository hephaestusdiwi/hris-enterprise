<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('salary_components', 'is_taxable')) {
            return;
        }

        Schema::table('salary_components', function (Blueprint $table) {
            // Mirror include_in_bpjs_base — dipakai Tax Engine buat nyusun penghasilan kena pajak.
            // Default true supaya component existing tetap kena pajak seperti perilaku implisit sebelumnya.
            $table->boolean('is_taxable')->default(true)->after('include_in_bpjs_base');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('salary_components', 'is_taxable')) {
            return;
        }

        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });
    }
};