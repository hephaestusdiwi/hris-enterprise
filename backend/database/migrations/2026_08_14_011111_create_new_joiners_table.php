<?php // backend/database/migrations/2026_08_12_000001_create_new_joiners_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_joiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status')->default('sent');
            // Data New Joiner — nullable sampai status Submitted. Field ini DIPILIH
            // berdasarkan audit kolom Employee yang belum tersedia dari Candidate/JobVacancy/Offering,
            // BUKAN full Applicant Form Talenta (sesuai instruksi Anda).
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // BUKAN status Talenta — penanda internal kita bahwa boundary "Proceed as employee"
            // sudah dilewati, dipakai Phase 7C nanti buat tau mana yang siap diproses. Bukan Employee.
            $table->timestamp('ready_for_employee_at')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_joiners');
    }
};