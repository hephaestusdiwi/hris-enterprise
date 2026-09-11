<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->string('working_type')->nullable()->after('employment_type_id');
            $table->decimal('salary_min', 15, 2)->nullable()->after('working_type');
            $table->decimal('salary_max', 15, 2)->nullable()->after('salary_min');
            // Default false — salary TIDAK ditampilkan ke career page kecuali HR
            // sengaja aktifkan, biar nggak tiba-tiba ke-expose ke job vacancy lama.
            $table->boolean('show_salary')->default(false)->after('salary_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->dropColumn(['working_type', 'salary_min', 'salary_max', 'show_salary']);
        });
    }
};