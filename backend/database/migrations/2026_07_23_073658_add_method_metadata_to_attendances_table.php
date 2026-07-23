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
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('clock_in_method')->nullable()->after('clock_in_distance_meters');
            $table->foreignId('clock_in_device_id')->nullable()->after('clock_in_method')->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('clock_in_branch_id')->nullable()->after('clock_in_device_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('clock_in_company_id')->nullable()->after('clock_in_branch_id')->constrained('companies')->nullOnDelete();

            $table->string('clock_out_method')->nullable()->after('clock_out_distance_meters');
            $table->foreignId('clock_out_device_id')->nullable()->after('clock_out_method')->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('clock_out_branch_id')->nullable()->after('clock_out_device_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('clock_out_company_id')->nullable()->after('clock_out_branch_id')->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_method', 'clock_in_device_id', 'clock_in_branch_id', 'clock_in_company_id',
                'clock_out_method', 'clock_out_device_id', 'clock_out_branch_id', 'clock_out_company_id',
            ]);
        });
    }
};
