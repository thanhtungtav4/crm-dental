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
            if (! Schema::hasColumn('zns_campaign_deliveries', 'normalized_phone')) {
                $table->string('normalized_phone', 32)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'attempt_count')) {
                $table->unsignedInteger('attempt_count')->default(0)->after('status');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'next_retry_at')) {
                $table->dateTime('next_retry_at')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'provider_status_code')) {
                $table->string('provider_status_code', 120)->nullable()->after('provider_message_id');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'provider_response')) {
                $table->json('provider_response')->nullable()->after('provider_status_code');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('customer_id')
                    ->constrained('branches')->nullOnDelete();
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'template_key_snapshot')) {
                $table->string('template_key_snapshot', 255)->nullable()->after('payload');
            }

            if (! Schema::hasColumn('zns_campaign_deliveries', 'template_id_snapshot')) {
                $table->string('template_id_snapshot', 255)->nullable()->after('template_key_snapshot');
            }
        });

        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            $table->index(['zns_campaign_id', 'normalized_phone'], 'zns_campaign_delivery_campaign_phone_idx');
            $table->index(['status', 'next_retry_at'], 'zns_campaign_delivery_status_retry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            $table->dropIndex('zns_campaign_delivery_campaign_phone_idx');
            $table->dropIndex('zns_campaign_delivery_status_retry_idx');
        });

        Schema::table('zns_campaign_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('zns_campaign_deliveries', 'template_id_snapshot')) {
                $table->dropColumn('template_id_snapshot');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'template_key_snapshot')) {
                $table->dropColumn('template_key_snapshot');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'provider_response')) {
                $table->dropColumn('provider_response');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'provider_status_code')) {
                $table->dropColumn('provider_status_code');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'next_retry_at')) {
                $table->dropColumn('next_retry_at');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'attempt_count')) {
                $table->dropColumn('attempt_count');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'normalized_phone')) {
                $table->dropColumn('normalized_phone');
            }

            if (Schema::hasColumn('zns_campaign_deliveries', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
