<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pola persis cash_advance_policy_category: pivot table berdiri
     * sendiri (punya id+timestamps sendiri), bukan pivot implisit
     * Laravel biasa.
     */
    public function up(): void
    {
        Schema::create('expense_policy_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['expense_policy_id', 'expense_category_id'], 'epc_policy_category_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_policy_category');
    }
};