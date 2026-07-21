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
            $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_in');
            $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
            $table->unsignedInteger('clock_in_distance_meters')->nullable()->after('clock_in_longitude');
            $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_out');
            $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
            $table->unsignedInteger('clock_out_distance_meters')->nullable()->after('clock_out_longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_in_distance_meters',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_distance_meters',
            ]);
        });
    }
};
