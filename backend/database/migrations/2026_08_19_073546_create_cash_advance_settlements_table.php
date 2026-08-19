<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu request bisa punya BEBERAPA baris settlement sepanjang waktu kalau
     * sempat rejected lalu diajukan ulang -- histori tidak ditimpa/dihapus,
     * baris lama tetap ada dengan status rejected.
     */
    public function up(): void
    {
        Schema::create('cash_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_request_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_actual_amount', 15, 2);
            $table->decimal('total_returned_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_advance_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_advance_category_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('actual_amount', 15, 2);
            $table->decimal('returned_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cash_advance_settlement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_settlement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_settlement_attachments');
        Schema::dropIfExists('cash_advance_settlement_items');
        Schema::dropIfExists('cash_advance_settlements');
    }
};