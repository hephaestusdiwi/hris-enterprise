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
        Schema::table('new_joiners', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('ready_for_employee_at')
                ->constrained('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_joiners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
