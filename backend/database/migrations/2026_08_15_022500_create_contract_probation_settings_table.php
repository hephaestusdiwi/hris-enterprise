<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Global (satu baris), belum per-company — konsisten sama keputusan Phase 1/3
// (config('contract_probation.*') juga global). Kalau nanti butuh per-company,
// itu perubahan lanjutan, bukan sekarang (jangan overengineer).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_probation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contract_reminder_days')->default(30);
            $table->unsignedInteger('probation_reminder_days')->default(30);
            $table->boolean('email_reminder_enabled')->default(true);
            $table->boolean('manager_reminder_enabled')->default(true);
            $table->timestamps();
        });

        // Seed satu baris default dari config existing — supaya begitu
        // migration jalan, behavior reminder TIDAK berubah sampai admin
        // benar-benar mengubah setting-nya lewat UI.
        DB::table('contract_probation_settings')->insert([
            'contract_reminder_days' => config('contract_probation.contract_reminder_days', 30),
            'probation_reminder_days' => config('contract_probation.probation_reminder_days', 30),
            'email_reminder_enabled' => true,
            'manager_reminder_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_probation_settings');
    }
};
