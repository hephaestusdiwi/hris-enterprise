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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('employment_type_id')
                ->nullable()
                ->after('employment_status_id')
                ->constrained('employment_types')
                ->nullOnDelete();

            $table->date('contract_start_date')->nullable()->after('resign_date');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->date('probation_end_date')->nullable()->after('contract_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropColumn([
                'contract_start_date',
                'contract_end_date',
                'probation_end_date',
            ]);
        });
    }
};
