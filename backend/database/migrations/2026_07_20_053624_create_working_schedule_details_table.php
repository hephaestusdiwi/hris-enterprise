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
        Schema::create('working_schedule_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('working_schedule_id')->constrained()->cascadeOnDelete();
            // ISO-8601: 1=Senin, 2=Selasa, ..., 7=Minggu. Konvensi ini dipakai
            // konsisten sampai ke Attendance (Carbon::dayOfWeekIso) nanti.
            $table->unsignedTinyInteger('day_of_week');
            // null = hari libur (tidak ada shift di hari itu)
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['working_schedule_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_schedule_details');
    }
};
