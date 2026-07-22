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
        Schema::table('employees', function (Blueprint $table) {
            $table->json('face_embedding')->nullable()->after('user_id');
            $table->string('face_embedding_model')->nullable()->after('face_embedding');
            $table->timestamp('face_registered_at')->nullable()->after('face_embedding_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['face_embedding', 'face_embedding_model', 'face_registered_at']);
        });
    }
};
