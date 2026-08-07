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
        Schema::create('bpjs_jkk_risk_class_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('risk_class'); // 1-5 sesuai regulasi
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->decimal('employer_rate_percentage', 5, 2);
            $table->timestamps();
 
            $table->unique(['risk_class', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpjs_jkk_risk_class_rates');
    }
};
