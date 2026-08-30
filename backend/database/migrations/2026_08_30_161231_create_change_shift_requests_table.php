<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_shift_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            // Snapshot shift normal (kalau ada) saat submit -- buat display,
            // BUKAN sumber kebenaran override (itu requested_shift_id +
            // status=approved, dicek live oleh DatabaseShiftOverrideResolver).
            $table->foreignId('current_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('requested_shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_shift_requests');
    }
};