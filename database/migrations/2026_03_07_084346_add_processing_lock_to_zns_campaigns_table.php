<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zns_campaigns', function (Blueprint $table) {
            $table->uuid('processing_token')->nullable()->after('failed_count');
            $table->dateTime('locked_at')->nullable()->after('processing_token');

            $table->index(['processing_token'], 'zns_campaign_processing_token_idx');
            $table->index(['status', 'locked_at'], 'zns_campaign_status_locked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('zns_campaigns', function (Blueprint $table) {
            $table->dropIndex('zns_campaign_processing_token_idx');
            $table->dropIndex('zns_campaign_status_locked_idx');

            $table->dropColumn(['processing_token', 'locked_at']);
        });
    }
};
