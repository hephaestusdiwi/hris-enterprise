<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Model/tabel TERPISAH dari salary_components (bukan menambah kolom ke
     * sana) — separation eksplisit antara recurring salary component (dipakai
     * Salary Structure/Payroll Regular) dan komponen payroll non-reguler
     * (Bonus/Incentive/Commission/One-Time Earning/One-Time Deduction/
     * Adjustment) yang sifatnya sekali pakai per employee per run.
     */
    public function up(): void
    {
        Schema::create('non_regular_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('category', 30);
            // Arah default (earning=true/deduction=false) — dipakai kalau
            // EmployeeNonRegularInput tidak mengisi override sendiri. Nullable
            // khusus kategori Adjustment (arah ditentukan per-input).
            $table->boolean('is_addition')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('include_in_bpjs_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_regular_payroll_components');
    }
};