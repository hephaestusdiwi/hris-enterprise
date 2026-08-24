<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Default 'self_submitted' -- baris existing otomatis kebackfill
            // nilai ini oleh DB saat migration jalan, behavior lama tidak
            // berubah sama sekali (LeaveRequestService::submit() tidak perlu
            // diubah, tidak perlu set 'source' eksplisit).
            $table->string('source')->default('self_submitted')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};