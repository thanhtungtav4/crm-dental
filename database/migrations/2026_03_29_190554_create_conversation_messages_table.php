<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('message_type', 40);
            $table->string('provider_message_id', 191)->nullable();
            $table->string('source_event_fingerprint', 128)->nullable();
            $table->text('body')->nullable();
            $table->json('payload_summary')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('next_retry_at')->nullable();
            $table->string('processing_token', 120)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('provider_status_code', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('message_at')->nullable();
            $table->timestamps();

            $table->unique('provider_message_id', 'conversation_messages_provider_message_unique');
            $table->unique('source_event_fingerprint', 'conversation_messages_event_fingerprint_unique');
            $table->index(['conversation_id', 'message_at'], 'conversation_messages_conversation_message_at_idx');
            $table->index(['status', 'next_retry_at'], 'conversation_messages_status_retry_idx');
            $table->index(['sent_by_user_id', 'status'], 'conversation_messages_sender_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
