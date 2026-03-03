<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_announcement_id')
                ->constrained('popup_announcements')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedSmallInteger('display_count')->default(0);
            $table->timestamp('last_displayed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['popup_announcement_id', 'user_id'], 'popup_announcement_user_unique');
            $table->index(['user_id', 'status'], 'popup_delivery_user_status_idx');
            $table->index(['status', 'delivered_at'], 'popup_delivery_status_delivered_idx');
            $table->index(['acknowledged_at', 'dismissed_at'], 'popup_delivery_ack_dismiss_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_announcement_deliveries');
    }
};
