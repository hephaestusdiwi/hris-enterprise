<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Auditability minimal buat Absence Deduction Reversal --
            // status baru "reversed" TIDAK butuh migration (kolom status
            // sudah plain string), tapi metadata siapa/kapan/kenapa
            // di-reverse genuinely belum ada field-nya sama sekali.
            $table->foreignId('reversed_by_user_id')->nullable()->after('decided_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by_user_id');
            $table->text('reversal_reason')->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by_user_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};