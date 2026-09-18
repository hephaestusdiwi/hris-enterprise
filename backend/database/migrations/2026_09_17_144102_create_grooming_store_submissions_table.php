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
        Schema::create('grooming_store_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained(); // store yang DIPERIKSA
            $table->foreignId('submitted_by_employee_id')->constrained('employees');
            $table->foreignId('grooming_standard_id')->constrained();
            $table->string('overall_result'); // pass | not_pass
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['branch_id', 'submitted_at']);
        });

        Schema::create('grooming_store_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grooming_store_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grooming_standard_item_id')->constrained();
            $table->string('result'); // pass | not_pass
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable(); // reserved untuk future per-item photo evidence
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grooming_store_submission_answers');
        Schema::dropIfExists('grooming_store_submissions');
    }
};