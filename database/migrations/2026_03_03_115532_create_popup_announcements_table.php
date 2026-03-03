<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title');
            $table->text('message');
            $table->string('priority', 20)->default('info');
            $table->string('status', 20)->default('draft');
            $table->json('target_role_names')->nullable();
            $table->json('target_branch_ids')->nullable();
            $table->boolean('require_ack')->default(false);
            $table->boolean('show_once')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'starts_at'], 'popup_announcements_status_starts_idx');
            $table->index(['status', 'ends_at'], 'popup_announcements_status_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_announcements');
    }
};
