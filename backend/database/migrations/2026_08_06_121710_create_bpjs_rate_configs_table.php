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
        Schema::create('bpjs_rate_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('program', 20); // kesehatan | jht | jkk | jkm (jp menyusul)
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            // Jkk: employee_rate_percentage selalu null (JKK 100% company, tarif dari risk class, bukan dari sini)
            $table->decimal('employee_rate_percentage', 5, 2)->nullable();
            $table->decimal('employer_rate_percentage', 5, 2)->nullable();
            $table->decimal('wage_base_cap', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
 
            $table->unique(['company_id', 'program', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpjs_rate_configs');
    }
};
