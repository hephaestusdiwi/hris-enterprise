<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * TrainingProgram = "Training" di Talenta LMS -- program/pelatihan
     * secara umum (mis. "Leadership Development Batch 2026"), yang di
     * dalamnya bisa punya banyak sesi/batch (lihat training_sessions).
     * Jadwal, lokasi, trainer, kuota ada di level SESSION -- bukan di
     * sini -- karena 1 training bisa punya banyak sesi (sesuai keputusan
     * scope Phase 1).
     *
     * pic_employee_id nullOnDelete (pola sama dengan CompanyObligation)
     * -- histori training TIDAK BOLEH ikut hilang kalau PIC-nya dihapus.
     */
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('internal'); // internal|external
            $table->string('organizer')->nullable(); // nama vendor/penyelenggara (relevan buat type=external)
            $table->foreignId('pic_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};