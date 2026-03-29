<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropUnique('conversation_messages_provider_message_unique');
            $table->dropUnique('conversation_messages_event_fingerprint_unique');

            $table->unique(
                ['conversation_id', 'provider_message_id'],
                'conversation_messages_conversation_provider_message_unique',
            );
            $table->unique(
                ['conversation_id', 'source_event_fingerprint'],
                'conversation_messages_conversation_event_fingerprint_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropUnique('conversation_messages_conversation_provider_message_unique');
            $table->dropUnique('conversation_messages_conversation_event_fingerprint_unique');

            $table->unique('provider_message_id', 'conversation_messages_provider_message_unique');
            $table->unique('source_event_fingerprint', 'conversation_messages_event_fingerprint_unique');
        });
    }
};
