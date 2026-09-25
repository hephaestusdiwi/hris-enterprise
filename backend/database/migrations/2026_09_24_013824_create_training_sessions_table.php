<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * TrainingSession = "Sesi"/batch di bawah 1 TrainingProgram -- jadwal
     * (start_at/end_at), trainer, lokasi, kuota ada di sini (BUKAN di
     * TrainingProgram), karena 1 program bisa punya banyak batch dengan
     * jadwal berbeda-beda (mis. Batch 1 offline Jakarta, Batch 2 online).
     *
     * trainer_employee_id dipisah dari trainer_name (string bebas) --
     * trainer bisa internal (pilih dari Employee) ATAU eksternal (nama
     * bebas, mis. konsultan luar), makanya 2 kolom terpisah, bukan 1 FK
     * wajib.
     *
     * Reminder H-30/14/7/1/0 dihitung dari start_at (lihat
     * TrainingReminderService), makanya kolomnya datetime (bukan date)
     * -- start_at juga dipakai buat tampilan jadwal ke peserta.
     */
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // mis. "Batch 1", "Sesi Pagi"
            $table->string('trainer_name')->nullable();
            $table->foreignId('trainer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('mode')->default('offline'); // online|offline|hybrid
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['training_program_id', 'status', 'start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};