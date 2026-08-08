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
        Schema::create('company_tax_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('default_tax_method', 20)->default('gross'); // gross | gross_up | netto
            $table->decimal('no_npwp_surcharge_percentage', 5, 2)->default(20);
            $table->decimal('position_cost_percentage', 5, 2)->default(5); // biaya jabatan 5%
            $table->decimal('position_cost_monthly_cap', 15, 2)->default(500000);
            $table->decimal('position_cost_annual_cap', 15, 2)->default(6000000);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_tax_settings');
    }
};
