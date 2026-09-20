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
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            // Nullable = company-wide (SOP, handbook, dst). Terisi = dokumen
            // ini "milik"/terkait 1 employee tertentu -- baik yang public
            // (mis. template kontrak khusus dia) maupun yang private
            // (KTP, rekening, kontrak, disciplinary -- WAJIB terisi untuk
            // 4 kategori ini, divalidasi di request, bukan di skema).
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category');
            // Diturunkan dari kategori (CompanyDocument::CATEGORIES), BUKAN
            // input bebas dari uploader -- disimpan sebagai kolom supaya
            // filter/query cepat tanpa perlu decode kategori tiap saat.
            $table->string('visibility');
            // Konteks module opsional (mis. "attendance", "payroll") untuk
            // dokumen public yang relevan ke module tertentu, bukan
            // company-wide murni. Tidak dipakai untuk gating permission.
            $table->string('module_context')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'category']);
            $table->index(['visibility', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};