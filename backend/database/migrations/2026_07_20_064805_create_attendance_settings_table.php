<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // null = default company-wide; diisi = override khusus branch ini
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('require_photo')->default(false);
            $table->boolean('require_location')->default(false);
            $table->decimal('office_latitude', 10, 7)->nullable();
            $table->decimal('office_longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_radius_meters')->default(100);
            $table->boolean('allow_mobile_checkin')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Mencegah dua override untuk branch yang sama
            $table->unique(['company_id', 'branch_id']);
        });

        // Partial unique index: maksimal satu baris "default company"
        // (branch_id IS NULL) per company. unique() biasa tidak cukup karena
        // Postgres menganggap banyak NULL itu semua berbeda.
        DB::statement('
            CREATE UNIQUE INDEX attendance_settings_company_default_unique
            ON attendance_settings (company_id)
            WHERE branch_id IS NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_settings_company_default_unique');
        Schema::dropIfExists('attendance_settings');
    }
};