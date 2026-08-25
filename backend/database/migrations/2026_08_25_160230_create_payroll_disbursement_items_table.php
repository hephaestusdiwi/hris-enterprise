<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_disbursement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_disbursement_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            // Snapshot data bank employee & jumlah transfer SAAT batch
            // digenerate — bukan live-join ke Employee/Payslip. Kalau data
            // rekening employee diubah belakangan, histori disbursement lama
            // harus tetap mencerminkan apa yang BENERAN dipakai waktu itu
            // (prinsip sama seperti Payslip snapshot terhadap salary/BPJS/PPh21).
            $table->string('employee_name');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder_name');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['payroll_disbursement_batch_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_disbursement_items');
    }
};
