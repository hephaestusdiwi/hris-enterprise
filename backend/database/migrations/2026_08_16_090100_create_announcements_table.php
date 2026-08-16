<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('announcement_category_id')->constrained()->restrictOnDelete();
            // 'all' = seluruh employee aktif. 'criteria' = resolve dari
            // announcement_targets (branch/department/position/job_level).
            $table->string('target_type')->default('all');
            // Minimal 2 state sesuai behavior Talenta yang direplikasi —
            // draft (belum ada recipient) & published (recipient sudah resolve).
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'announcement_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
