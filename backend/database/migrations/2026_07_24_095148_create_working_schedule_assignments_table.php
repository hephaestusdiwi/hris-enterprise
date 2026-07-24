<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('working_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX working_schedule_assignments_active_target_unique
             ON working_schedule_assignments (target_type, target_id)
             WHERE deleted_at IS NULL AND is_active = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS working_schedule_assignments_active_target_unique');
        Schema::dropIfExists('working_schedule_assignments');
    }
};