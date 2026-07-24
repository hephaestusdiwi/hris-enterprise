<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('detected_overtime_minutes')->nullable()->after('within_grace');
            $table->integer('approved_overtime_minutes')->nullable()->after('detected_overtime_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['detected_overtime_minutes', 'approved_overtime_minutes']);
        });
    }
};