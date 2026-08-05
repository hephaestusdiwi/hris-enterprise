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
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->unsignedSmallInteger('payroll_period_year');
            $table->unsignedTinyInteger('payroll_period_month');
            $table->decimal('principal_portion', 15, 2);
            $table->decimal('interest_portion', 15, 2)->default(0);
            // original_amount = nominal hasil hitung awal, immutable, buat audit trail.
            // amount = nominal aktif sekarang (bisa berubah lewat Loan Adjustment/Reschedule nanti).
            $table->decimal('original_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('scheduled');
            $table->timestamp('paid_at')->nullable();
            // Diisi nanti oleh Payroll Generator begitu cicilan ini beneran dipotong di payroll.
            $table->foreignId('employee_deduction_id')->nullable()->constrained('employee_deductions')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['payroll_period_year', 'payroll_period_month', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
