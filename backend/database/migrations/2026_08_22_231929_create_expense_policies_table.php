<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Berbeda dari CashAdvancePolicy/ReimbursementPolicy (keduanya tidak
     * company-scoped): ExpensePolicy WAJIB company_id, karena harus bisa
     * memvalidasi "category yang di-attach berasal dari company yang sama"
     * (lihat expense_policy_category). Tidak ada code/softDeletes/destroy --
     * konsisten dengan kedua Policy existing (dinonaktifkan via is_active
     * saja, tidak pernah dihapus).
     */
    public function up(): void
    {
        Schema::create('expense_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('effective_date');
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_policies');
    }
};