<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * unique(employee_id, effective_date) -- BUKAN termasuk
     * expense_policy_id -- supaya 1 employee bisa transisi dari Policy A
     * ke Policy B lewat baris baru, tapi tidak bisa punya 2 assignment
     * yang mulai di tanggal yang sama. effective_date immutable setelah
     * dibuat (tidak ada API yang mengizinkan mengubahnya), ganti policy =
     * insert baris baru, riwayat lama tidak pernah ditimpa.
     */
    public function up(): void
    {
        Schema::create('expense_policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_policy_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_policy_assignments');
    }
};