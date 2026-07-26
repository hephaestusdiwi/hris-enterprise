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
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('eligible_from');
            $table->decimal('initial_quota', 6, 2)->nullable();
            $table->decimal('carry_over_days', 6, 2)->default(0);
            $table->date('carry_over_expiry_date')->nullable();
            $table->decimal('used_days', 6, 2)->default(0);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
