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
        // Setiap baris = SATU VERSI standard (bukan tabel parent+child terpisah).
        // "Buat versi baru" = insert baris baru dengan version_number+1, BUKAN
        // update baris lama. Baris yang statusnya 'active' pernah dipakai untuk
        // submission — begitu ada versi baru yang di-publish, baris lama pindah
        // ke 'archived' dan TIDAK PERNAH diedit lagi (items-nya ikut freeze).
        Schema::create('grooming_standards', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // self | store
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->date('effective_date');
            $table->string('status')->default('draft'); // draft | active | archived
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('grooming_standard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grooming_standard_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('mandatory')->default(true);
            $table->boolean('requires_note_on_fail')->default(true);
            $table->boolean('requires_photo')->default(false); // reserved, dipakai kalau Grooming Store aktifkan foto per-item di masa depan
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grooming_standard_items');
        Schema::dropIfExists('grooming_standards');
    }
};