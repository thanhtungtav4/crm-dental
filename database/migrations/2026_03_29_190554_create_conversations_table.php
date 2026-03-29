<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('channel_key', 120);
            $table->string('external_conversation_key', 191);
            $table->string('external_user_id', 120);
            $table->string('external_display_name')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('open');
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('latest_message_preview')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('last_inbound_at')->nullable();
            $table->dateTime('last_outbound_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'channel_key', 'external_conversation_key'],
                'conversations_provider_channel_external_unique',
            );
            $table->index(['branch_id', 'status', 'last_message_at'], 'conversations_branch_status_last_message_idx');
            $table->index(['assigned_to', 'status'], 'conversations_assigned_status_idx');
            $table->index(['customer_id', 'last_message_at'], 'conversations_customer_last_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
