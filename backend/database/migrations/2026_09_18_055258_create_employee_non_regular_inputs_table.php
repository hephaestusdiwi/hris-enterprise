<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input nominal non-reguler per employee per periode payroll (Bonus,
     * Incentive, Commission, One-Time Earning/Deduction, Adjustment).
     * Struktur & lifecycle status (draft->ready->processed->void) SENGAJA
     * dibuat mirror persis employee_allowances/employee_deductions — supaya
     * Payroll Calculation Engine bisa pakai pola query 'ready' + periode yang
     * SAMA PERSIS kayak yang sudah ada, bukan mekanisme baru.
     */
    public function up(): void
    {
        Schema::create('employee_non_regular_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('non_regular_payroll_component_id')
                ->constrained('non_regular_payroll_components')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('payroll_period_year');
            $table->unsignedTinyInteger('payroll_period_month');
            $table->decimal('amount', 15, 2);
            // Arah efektif entry ini (true=earning/penambah, false=deduction/
            // pengurang) — wajib diisi eksplisit di service (fallback ke
            // resolvedIsAddition() komponen kalau tidak dioverride di form).
            $table->boolean('is_addition');
            $table->text('note')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'payroll_period_year', 'payroll_period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_non_regular_inputs');
    }
};