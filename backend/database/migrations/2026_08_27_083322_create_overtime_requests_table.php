<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('planned_minutes');
            $table->text('reason');
            $table->string('status');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();

            // Fase "claim" -- diisi setelah tanggal berlalu & employee
            // konfirmasi overtime beneran dikerjain. attendance_id nullable
            // dengan sengaja (belum ke-link sampai claim terjadi).
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('actual_overtime_minutes')->nullable();
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};