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
        Schema::create('ptkp_configs', function (Blueprint $table) {
            $table->id();
            $table->string('ptkp_status', 10); // tk0, tk1, tk2, tk3, k0, k1, k2, k3
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->decimal('annual_amount', 15,2);
            $table->timestamps();

            $table->unique(['ptkp_status', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ptkp_configs');
    }
};
