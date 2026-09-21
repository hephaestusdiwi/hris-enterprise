<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recipient reminder: User (user_id spesifik), Pic (recipient
     * ikut/turut ke pic_employee_id milik obligation -- tidak butuh kolom
     * tambahan, cukup ditandai type='pic'), atau Role (nama role Spatie,
     * di-notify SEMUA user dengan role itu di company yang sama dengan
     * obligation). Ketiga type simpan di 1 tabel (bukan 3 tabel terpisah),
     * konsisten dengan prinsip "1 module generic" yang diminta.
     *
     * TIDAK pakai unique constraint DB buat cegah recipient duplikat --
     * user_id/role nullable bikin unique index Postgres gak efektif (NULL
     * tidak dianggap sama dengan NULL). Dedup cukup dicek di
     * CompanyObligationRecipientController sebelum insert, konsisten
     * dengan gaya dedup reminder module ini (query, bukan constraint).
     */
    public function up(): void
    {
        Schema::create('company_obligation_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_obligation_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('company_obligation_recipients');
    }
};