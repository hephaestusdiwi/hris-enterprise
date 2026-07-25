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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_paid')->default(true);
            $table->unsignedInteger('max_days_per_year')->nullable();
            $table->unsignedInteger('min_service_months')->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->string('gender_restriction')->nullable();
            $table->boolean('carry_over_allowed')->default(false);
            $table->unsignedInteger('carry_over_max_days')->nullable();
            $table->unsignedTinyInteger('carry_over_expiry_month')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allow_half_day')->default(false);
            $table->boolean('allow_hourly')->default(false);
            $table->boolean('requires_balance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
