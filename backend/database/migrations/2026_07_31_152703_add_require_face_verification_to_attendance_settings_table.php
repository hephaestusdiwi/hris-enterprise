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
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Beda dari require_photo (cuma nyimpen foto biasa) — ini verifikasi
            // identitas: foto dicocokkan ke face_embedding milik employee sendiri
            // (1-ke-1) lewat Face Recognition Service (STEP 35).
            $table->boolean('require_face_verification')->default(false)->after('require_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('require_face_verification');
        });
    }
};
