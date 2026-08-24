<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Persis pola reimbursement_approval_tables / cash_advance_approval_tables
     * -- dibutuhkan supaya ExpenseClaimApprovalService::initiate() bisa
     * membuat approval trail yang nyata (bukan cuma auto-approve semua).
     * Decide layer (STEP 4C) belum dibuat, tapi tabelnya harus ada dari
     * sekarang supaya claim yang masuk approval flow beneran punya jejak.
     */
    public function up(): void
    {
        Schema::create('expense_claim_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step_sequence');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_claim_approval_step_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_claim_approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_claim_approval_step_decisions');
        Schema::dropIfExists('expense_claim_approval_requests');
    }
};