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
        Schema::create('candidate_blacklists', function (Blueprint $table) {
            $table->id();
            // Di-key by EMAIL (bukan candidate_id) dan disimpan lowercase — supaya
            // orang yang sama tetap ke-block walau apply ulang bikin Candidate
            // record baru dengan job_vacancy berbeda. candidate_id di bawah cuma
            // referensi historis (dari kandidat mana blacklist ini pertama dibuat),
            // TIDAK dipakai buat matching.
            $table->string('email');
            $table->string('full_name')->nullable();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->foreignId('blacklisted_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('blacklisted_at');
            $table->timestamps();

            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_blacklists');
    }
};
