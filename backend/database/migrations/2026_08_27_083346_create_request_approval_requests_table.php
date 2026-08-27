<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_request_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('current_step_sequence');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approval_requests');
    }
};