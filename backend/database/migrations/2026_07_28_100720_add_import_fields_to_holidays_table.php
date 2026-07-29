<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - `source` menandai asal data: 'manual' (dibuat/di-edit HR) atau 'import' (dari provider).
     * - `external_id` adalah kunci idempoten untuk UPSERT, contoh: "nager:ID:2026-01-01".
     *   Bersifat unique & nullable (holiday manual tidak punya external_id).
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('is_active');
            $table->string('external_id')->nullable()->unique()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['source', 'external_id']);
        });
    }
};