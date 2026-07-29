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
        Schema::create('salary_structure_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('override_amount', 15, 2)->nullable();
            $table->decimal('override_percentage_value', 5, 2)->nullable();
            $table->string('override_percentage_base')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_structure_details');
    }
};
