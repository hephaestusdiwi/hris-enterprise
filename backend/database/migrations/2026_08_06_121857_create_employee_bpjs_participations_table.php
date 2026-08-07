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
        Schema::create('employee_bpjs_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
 
            // BPJS Kesehatan
            $table->string('bpjs_health_number', 20)->nullable();
            $table->unsignedTinyInteger('bpjs_health_family_count')->default(0);
            $table->date('bpjs_health_start_date')->nullable(); // null = pakai join date employee
            $table->string('bpjs_health_cost_bearer', 20)->default('default');
 
            // BPJS Ketenagakerjaan (JHT, JKK, JKM)
            $table->string('bpjs_employment_number', 20)->nullable(); // KPJ
            $table->string('bpjs_registration_npp_number', 50)->nullable(); // FK logis ke bpjs_company_registrations.npp_number
            $table->date('bpjs_employment_start_date')->nullable(); // null = pakai join date employee
            $table->string('jht_cost_bearer', 20)->default('default');
 
            $table->timestamps();
 
            $table->unique('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs_participations');
    }
};
