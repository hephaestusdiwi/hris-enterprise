<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subcategory sengaja TIDAK punya company_id sendiri -- company
     * selalu diturunkan lewat expense_category_id (ExpenseSubcategory
     * belongsTo ExpenseCategory belongsTo Company). Ini menghindari
     * kolom company_id yang bisa desync dari kategori induknya.
     * Code unique per kategori induk (bukan per company), konsisten
     * dengan level relasi yang paling spesifik.
     */
    public function up(): void
    {
        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['expense_category_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_subcategories');
    }
};