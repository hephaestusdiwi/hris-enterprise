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
        Schema::create('hiring_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('replacement_for_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reason');
            $table->string('employment_type');
            $table->unsignedInteger('headcount_requested');
            $table->unsignedInteger('headcount_filled')->default(0);
            $table->date('target_start_date')->nullable();
            $table->text('justification');
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hiring_requisitions');
    }
};
