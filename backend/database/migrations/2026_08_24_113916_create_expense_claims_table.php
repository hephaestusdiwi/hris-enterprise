<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-item claim (bukan claim+items seperti Reimbursement/CashAdvance)
     * -- keputusan terkunci STEP 4A. expense_policy_assignment_id WAJIB
     * disimpan (snapshot assignment yang dipakai saat submit, bukan
     * di-resolve ulang tiap baca -- historical integrity). Tidak ada FK
     * cascadeOnDelete ke assignment/category/subcategory: assignment/category
     * tidak punya endpoint delete sama sekali di repo ini, tapi tetap
     * sengaja tidak cascade supaya claim historis tidak pernah ikut lenyap
     * kalau suatu saat ada delete path.
     *
     * Kolom payment (paid_at/paid_by_user_id/payment_note) disiapkan di
     * schema sesuai audit STEP 4 (pola disbursed_at Reimbursement), TAPI
     * endpoint mark-as-paid belum dibuat di STEP 4A (STEP 4D).
     */
    public function up(): void
    {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_policy_assignment_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('expense_subcategory_id')->nullable()->constrained();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('status');
            $table->timestamp('decided_at')->nullable();
            $table->text('cancel_reason')->nullable();

            // Payment metadata -- kolom disiapkan, endpoint STEP 4D.
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('payment_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
    }
};