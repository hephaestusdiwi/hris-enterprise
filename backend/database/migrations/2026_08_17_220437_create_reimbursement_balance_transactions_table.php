<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_balance_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reimbursement_balance_id')
                ->constrained('reimbursement_balances')
                ->cascadeOnDelete();

            $table->string('type');

            $table->decimal('amount', 15, 2);

            $table->decimal('running_balance', 15, 2);

            $table->unsignedBigInteger('reimbursement_request_id')
                ->nullable();

            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_balance_transactions');
    }
};