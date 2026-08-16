<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Assign/Balance Employee": hasil assign satu Policy ke satu Employee.
     * Balance itu sendiri (assigned_amount) tidak pernah di-update langsung --
     * histori perubahan/pemakaian ada di reimbursement_balance_transactions
     * (ledger), sama seperti prinsip yang sudah dipakai di Loan installment.
     */
    public function up(): void
    {
        Schema::create('reimbursement_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('reimbursement_policy_id')
                ->constrained('reimbursement_policies')
                ->cascadeOnDelete();

            $table->date('effective_date');
            $table->date('ended_at')->nullable();

            // Nullable = unlimited.
            $table->decimal('assigned_amount', 15, 2)->nullable();

            // Status balance: active / ended.
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index([
                'employee_id',
                'reimbursement_policy_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursement_balances');
    }
};