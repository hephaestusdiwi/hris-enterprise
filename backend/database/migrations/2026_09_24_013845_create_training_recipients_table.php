<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recipient TAMBAHAN (di luar peserta) yang di-reminder tiap sesi di
     * bawah sebuah TrainingProgram akan mulai -- User/Pic/Role, persis
     * pola company_obligation_recipients. Peserta (TrainingParticipant)
     * OTOMATIS jadi recipient tanpa perlu didaftar di sini -- tabel ini
     * cuma buat recipient EKSTRA (mis. atasan/HR yang mau ikut dapat
     * reminder tiap ada sesi mau mulai).
     *
     * Ditaruh di level PROGRAM (bukan per-session) supaya HR cukup
     * setup sekali per program, otomatis berlaku ke semua sesi di
     * bawahnya.
     */
    public function up(): void
    {
        Schema::create('training_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_recipients');
    }
};