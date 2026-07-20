<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->unsignedInteger('late_tolerance_minutes')->default(0);
            $table->unsignedInteger('check_in_before_minutes')->default(0);
            $table->unsignedInteger('check_out_after_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};