<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_annual_tax_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('tax_method_applied', 20);
            $table->decimal('total_gross_annual', 15, 2);
            $table->decimal('position_cost_deduction', 15, 2);
            $table->decimal('pension_deduction', 15, 2);
            $table->decimal('ptkp_amount', 15, 2);
            $table->decimal('net_annual_income', 15, 2);
            $table->decimal('pkp', 15, 2);
            $table->decimal('annual_tax_pasal17', 15, 2);
            $table->decimal('total_withheld_prior_months', 15, 2);
            $table->decimal('final_period_adjustment', 15, 2);
            $table->decimal('gross_up_allowance', 15, 2);
            $table->boolean('no_tax_id_surcharge_applied')->default(false);
            $table->timestamps();

            $table->unique('payslip_id');
            $table->index(['employee_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_annual_tax_reconciliations');
    }
};