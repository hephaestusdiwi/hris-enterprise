<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_revisions');
    }
};