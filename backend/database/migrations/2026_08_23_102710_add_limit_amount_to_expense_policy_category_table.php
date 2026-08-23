<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NULL = unlimited. Ditaruh di pivot existing (bukan tabel terpisah)
     * karena grain bisnisnya persis expense_policy+expense_category --
     * sama seperti pivot itu sendiri, jadi tidak perlu tabel baru.
     */
    public function up(): void
    {
        Schema::table('expense_policy_category', function (Blueprint $table) {
            $table->decimal('limit_amount', 15, 2)->nullable()->after('expense_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_policy_category', function (Blueprint $table) {
            $table->dropColumn('limit_amount');
        });
    }
};