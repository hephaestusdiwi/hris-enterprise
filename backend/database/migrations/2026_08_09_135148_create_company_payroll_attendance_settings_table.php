<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_payroll_attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('enable_attendance_integration')->default(true);

            // Formula lembur [Regulasi Pemerintah - Kepmenaker 102/2004, disederhanakan]
            $table->unsignedSmallInteger('overtime_hourly_divisor')->default(173);
            $table->decimal('overtime_multiplier_first_hour', 4, 2)->default(1.5);
            $table->decimal('overtime_multiplier_next_hours', 4, 2)->default(2.0);
            $table->foreignId('overtime_salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();

            $table->decimal('late_deduction_per_minute', 15, 2)->nullable();
            $table->foreignId('late_deduction_salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();

            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payroll_attendance_settings');
    }
};