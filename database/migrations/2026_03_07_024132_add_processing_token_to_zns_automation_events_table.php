<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zns_automation_events')) {
            return;
        }

        Schema::table('zns_automation_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('zns_automation_events', 'processing_token')) {
                $table->string('processing_token', 64)->nullable()->after('locked_at');
                $table->index(['status', 'processing_token'], 'zns_auto_events_status_processing_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('zns_automation_events')) {
            return;
        }

        Schema::table('zns_automation_events', function (Blueprint $table): void {
            if (Schema::hasColumn('zns_automation_events', 'processing_token')) {
                $table->dropIndex('zns_auto_events_status_processing_idx');
                $table->dropColumn('processing_token');
            }
        });
    }
};
