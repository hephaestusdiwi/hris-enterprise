<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_disbursement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            // Terikat ke revisi SPESIFIK saat batch di-generate (bukan cuma
            // payroll_run_id) — konsisten sama prinsip immutability di
            // seluruh module Payroll: batch lama tidak boleh "ikut berubah"
            // kalau payroll direcalculate lagi setelah batch ini dibuat.
            $table->foreignId('payroll_run_revision_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('generated'); // generated|sent|confirmed|failed
            $table->decimal('total_amount', 15, 2);
            $table->unsignedInteger('total_employee_count');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_disbursement_batches');
    }
};
