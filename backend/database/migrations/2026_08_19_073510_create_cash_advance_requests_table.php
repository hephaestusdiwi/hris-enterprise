<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_policy_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->date('date_of_use');
            $table->text('notes')->nullable();
            // Denormalized sum of items.amount -- source of truth tetap items.
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('pending_approval');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->foreignId('disbursed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('disbursement_note')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_advance_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('cash_advance_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_attachments');
        Schema::dropIfExists('cash_advance_request_items');
        Schema::dropIfExists('cash_advance_requests');
    }
};