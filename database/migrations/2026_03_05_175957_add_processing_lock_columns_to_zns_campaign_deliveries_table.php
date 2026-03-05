<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zns_campaign_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('zns_campaign_deliveries', 'processing_token')) {
                $table->string('processing_token', 64)->nullable()->after('status');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'locked_at')) {
                $table->dateTime('locked_at')->nullable()->after('processing_token');
            }
        });

        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            $table->index(['zns_campaign_id', 'processing_token'], 'zns_campaign_delivery_processing_idx');
            $table->index(['zns_campaign_id', 'status', 'next_retry_at'], 'zns_campaign_delivery_claim_scan_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            $table->dropIndex('zns_campaign_delivery_processing_idx');
            $table->dropIndex('zns_campaign_delivery_claim_scan_idx');
        });

        Schema::table('zns_campaign_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('zns_campaign_deliveries', 'locked_at')) {
                $table->dropColumn('locked_at');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'processing_token')) {
                $table->dropColumn('processing_token');
            }
        });
    }
};
