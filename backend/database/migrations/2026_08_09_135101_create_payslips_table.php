<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->decimal('gross_earning', 15, 2);
            $table->decimal('structural_deduction', 15, 2);
            $table->decimal('manual_deduction_total', 15, 2);
            $table->decimal('bpjs_employee_total', 15, 2);
            $table->decimal('bpjs_employer_total', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('loan_deduction_total', 15, 2);
            $table->decimal('net_pay', 15, 2);

            $table->boolean('is_published')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_revision_id', 'employee_id']);
            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};