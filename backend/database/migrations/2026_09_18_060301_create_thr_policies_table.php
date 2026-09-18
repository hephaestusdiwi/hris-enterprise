<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thr_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Aturan standar Permenaker 6/2016: masa kerja >= 1 bulan berhak
            // THR prorata, >= 12 bulan berhak THR penuh. Dibuat configurable
            // per company (bukan hard-code) sesuai kebutuhan section D.
            $table->unsignedSmallInteger('minimum_service_months')->default(1);
            $table->unsignedSmallInteger('full_service_months')->default(12);
            // THR secara default TIDAK masuk basis BPJS (praktik umum di
            // Indonesia) tapi tetap dibuat configurable per company sesuai
            // section F. is_taxable THR sengaja TIDAK configurable — THR
            // wajib kena pajak menurut regulasi, jadi fixed true di engine.
            $table->boolean('include_in_bpjs_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thr_policies');
    }
};