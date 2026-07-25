<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Path relatif di disk "public" (storage/app/public/...), bukan URL
            // penuh — URL di-generate on-the-fly via Storage::url() di Resource/
            // response, biar nggak ke-hardcode APP_URL di database.
            $table->string('photo_path')->nullable()->after('qr_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
