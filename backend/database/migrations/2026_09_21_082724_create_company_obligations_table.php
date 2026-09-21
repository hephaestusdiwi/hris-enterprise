<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1 module generic buat 3 kebutuhan (Sewa Ruko, MOU Legal, Jatuh Tempo
     * Piutang) -- dibedakan lewat kolom `type` (lihat CompanyObligation::TYPES),
     * BUKAN 3 tabel/module terpisah. `amount` nullable karena cuma relevan
     * buat sewa/piutang, MOU Legal biasanya tidak punya nominal.
     *
     * `pic_employee_id` nullOnDelete (pola approver_employee_id di
     * approval_steps) -- obligation historis TIDAK BOLEH ikut hilang kalau
     * PIC-nya suatu saat dihapus dari Employee.
     *
     * `status` dipakai reminder service buat stop kirim reminder begitu
     * obligation Completed/Cancelled (pola Loan/CashAdvance: status string,
     * bukan enum DB, konsisten dengan konvensi module lain di project ini).
     */
    public function up(): void
    {
        Schema::create('company_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('pic_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_obligations');
    }
};