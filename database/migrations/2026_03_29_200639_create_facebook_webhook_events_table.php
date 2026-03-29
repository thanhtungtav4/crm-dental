<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_fingerprint', 128)->unique();
            $table->string('event_name', 80)->nullable();
            $table->string('page_id', 120)->nullable();
            $table->string('sender_id', 120)->nullable();
            $table->string('recipient_id', 120)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('normalize_status', 40)->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->dateTime('normalized_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'normalize_status'], 'facebook_webhook_events_page_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_webhook_events');
    }
};
