<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('category');
            $table->boolean('is_addition')->default(true);
            $table->string('calculation_method');
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage_value', 5, 2)->nullable();
            $table->string('percentage_base')->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('include_in_bpjs_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};