<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Assign/Balance Employee": hasil assign satu Policy ke satu Employee.
     *
     * Balance tidak di-update langsung ketika terjadi pemakaian.
     * Seluruh perubahan saldo dicatat melalui reimbursement_balance_transactions
     * sebagai ledger.
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

            // Nullable = unlimited.
            $table->decimal('assigned_amount', 15, 2)->nullable();

            $table->date('effective_date');
            $table->date('expiration_date')->nullable();

            $table->string('status')->default('active');

            $table->timestamp('stopped_at')->nullable();
            $table->text('stop_reason')->nullable();

            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'employee_id',
                'reimbursement_policy_id',
            ]);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_balances');
    }
};