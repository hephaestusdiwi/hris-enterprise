<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_request_approval_step_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approval_step_decisions');
    }
};