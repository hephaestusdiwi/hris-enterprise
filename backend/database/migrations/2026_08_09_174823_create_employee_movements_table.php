<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type');
            $table->date('effective_date');
            $table->string('status')->default('pending_approval');

            // Field lifecycle relevan SEBELUM/SESUDAH movement ini, disimpan sebagai
            // changeset JSON — bukan snapshot lengkap seluruh kolom Employee.
            // Immutable begitu ditulis (history tidak boleh diedit).
            $table->json('before_snapshot');
            $table->json('after_snapshot');

            $table->text('reason')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
            // SENGAJA tidak ada softDeletes() — history bersifat immutable,
            // "batal" direpresentasikan lewat status Cancelled/Rejected, bukan delete.

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_movements');
    }
};
