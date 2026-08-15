<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loans', 'interest_type')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->string('interest_type')
                    ->default('flat');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loans', 'interest_type')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropColumn('interest_type');
            });
        }
    }
};