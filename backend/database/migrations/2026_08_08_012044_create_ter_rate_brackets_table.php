<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ter_rate_brackets', function (Blueprint $table) {
            $table->id();
            $table->char('category', 1); // A, B, C
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->decimal('income_from', 15, 2);
            $table->decimal('income_to', 15, 2)->nullable(); // null = tidak terbatas (lapisan teratas)
            $table->decimal('rate_percentage', 5, 2);
            $table->timestamps();

            $table->index(['category', 'effective_date', 'income_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_rate_brackets');
    }
};
