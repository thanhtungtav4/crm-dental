<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zalo_webhook_events', function (Blueprint $table) {
            $table->string('normalize_status', 40)->nullable()->after('processed_at');
            $table->foreignId('conversation_id')->nullable()->after('normalize_status')->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->after('conversation_id')->constrained('conversation_messages')->nullOnDelete();
            $table->dateTime('normalized_at')->nullable()->after('message_id');
            $table->text('error_message')->nullable()->after('normalized_at');

            $table->index(['normalize_status', 'normalized_at'], 'zalo_webhook_events_normalize_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('zalo_webhook_events', function (Blueprint $table) {
            $table->dropIndex('zalo_webhook_events_normalize_status_idx');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('message_id');
            $table->dropColumn([
                'normalize_status',
                'normalized_at',
                'error_message',
            ]);
        });
    }
};
