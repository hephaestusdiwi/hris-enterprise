<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_date');
            // Jumlah hari setelah disbursement employee wajib submit settlement.
            // Nullable = tidak ada batas waktu ketat (ditampilkan saja di UI).
            $table->unsignedInteger('settlement_due_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_advance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Policy <-> Category: kategori mana yang boleh dipakai pada policy itu.
        Schema::create('cash_advance_policy_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cash_advance_policy_id', 'cash_advance_category_id'], 'cap_policy_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_policy_category');
        Schema::dropIfExists('cash_advance_categories');
        Schema::dropIfExists('cash_advance_policies');
    }
};