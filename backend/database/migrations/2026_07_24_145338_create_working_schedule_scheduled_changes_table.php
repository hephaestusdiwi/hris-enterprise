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
        Schema::create('working_schedule_scheduled_changes', function (Blueprint $table) {
            $table->id();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('working_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('status')->default('pending');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_schedule_scheduled_changes');
    }
};
