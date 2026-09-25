<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1 baris = 1 karyawan terdaftar di 1 TrainingSession (bukan di level
     * program -- karyawan daftar ke batch tertentu). employee_id
     * cascadeOnDelete (pola ExpenseClaim: participation record "milik"
     * employee, beda dari pic_employee_id yang nullOnDelete karena cuma
     * "ditugaskan").
     *
     * Sertifikat SENGAJA belum ada kolomnya di Phase 1 (keputusan user:
     * skip dulu) -- kalau nanti ditambah, cukup migration baru nambah
     * kolom, tidak perlu redesign tabel.
     */
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('registered'); // registered|attended|absent|completed|cancelled
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};