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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_balance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_days', 6, 2);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
