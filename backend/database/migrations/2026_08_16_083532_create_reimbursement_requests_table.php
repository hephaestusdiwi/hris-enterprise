<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reimbursement_requests',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('employee_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'reimbursement_policy_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'reimbursement_balance_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->date('transaction_date');

                $table->decimal(
                    'total_amount',
                    15,
                    2
                );

                $table->text('notes')
                    ->nullable();

                $table->string('status')
                    ->default('pending');

                $table->timestamp('decided_at')
                    ->nullable();

                $table->text('cancel_reason')
                    ->nullable();

                $table->timestamp('disbursed_at')
                    ->nullable();

                $table->foreignId('disbursed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('disbursement_note')
                    ->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'reimbursement_request_items',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'reimbursement_request_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'reimbursement_benefit_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->decimal(
                    'amount',
                    15,
                    2
                );

                $table->text('notes')
                    ->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'reimbursement_attachments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'reimbursement_request_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('file_path');
                $table->string('file_name');

                $table->unsignedBigInteger(
                    'file_size'
                );

                $table->string('mime_type');

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reimbursement_attachments'
        );

        Schema::dropIfExists(
            'reimbursement_request_items'
        );

        Schema::dropIfExists(
            'reimbursement_requests'
        );
    }
};