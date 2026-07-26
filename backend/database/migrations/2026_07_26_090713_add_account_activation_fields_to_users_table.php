<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default 'active' — semua User lama (dibuat sebelum fitur invite
            // ini ada) otomatis dianggap aktif, tidak perlu backfill/migrasi data.
            $table->string('account_status')->default('active')->after('password');
            $table->timestamp('invited_at')->nullable()->after('account_status');
            $table->string('activation_token_hash')->nullable()->after('invited_at');
            $table->timestamp('activation_token_expires_at')->nullable()->after('activation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_status', 'invited_at', 'activation_token_hash', 'activation_token_expires_at']);
        });
    }
};
