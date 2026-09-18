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
        Schema::create('grooming_self_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Snapshot store tempat employee bertugas SAAT submission — bukan
            // live-lookup ke Employee.branch_id, biar histori tetap akurat kalau
            // employee pindah branch di kemudian hari.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // Sengaja TIDAK cascadeOnDelete/nullOnDelete ke grooming_standards —
            // standard yang sudah pernah dipakai submission tidak boleh terhapus.
            // Dibiarkan restrict (default FK behavior).
            $table->foreignId('grooming_standard_id')->constrained();
            $table->string('overall_result'); // pass | not_pass
            $table->string('photo_path');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['employee_id', 'submitted_at']);
        });

        Schema::create('grooming_self_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grooming_self_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grooming_standard_item_id')->constrained();
            $table->string('result'); // pass | not_pass
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grooming_self_submission_answers');
        Schema::dropIfExists('grooming_self_submissions');
    }
};