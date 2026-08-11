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
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Nullable dengan sengaja: attendance untuk tanggal ini bisa saja
            // belum ada row-nya sama sekali (mis. sistem down, employee gagal
            // clock-in). Kalau terisi, approval akan UPDATE row ini. Kalau
            // null, approval akan CREATE Attendance baru lalu link balik ke
            // sini.
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('requested_clock_in')->nullable();
            $table->dateTime('requested_clock_out')->nullable();
            $table->text('reason');
            $table->string('status');
            $table->timestamp('submitted_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_requests');
    }
};
