<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('source');
            $table->string('cv_path')->nullable();
            $table->string('status')->default('applied');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('converted_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('held_at')->nullable();
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['job_vacancy_id', 'status']);
        });

        Schema::create('candidate_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_stage_histories');
        Schema::dropIfExists('candidates');
    }
};