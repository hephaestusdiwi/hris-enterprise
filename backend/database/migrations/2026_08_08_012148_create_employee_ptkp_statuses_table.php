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
        Schema::create('employee_ptkp_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('ptkp_status', 10); // tk0, tk1, ..., k3
            $table->unsignedSmallInteger('tax_year'); // status berlaku MULAI tahun pajak ini (mirror "PTKP Status Adjustment" Talenta)
            $table->date('effective_date'); // selalu 1 Januari tax_year — disimpan eksplisit biar resolver seragam sama modul lain
            $table->timestamps();

            $table->unique(['employee_id', 'tax_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_ptkp_statuses');
    }
};
