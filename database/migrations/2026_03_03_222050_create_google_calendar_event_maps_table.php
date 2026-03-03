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
        Schema::create('google_calendar_event_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('calendar_id');
            $table->string('google_event_id')->index();
            $table->string('payload_checksum', 64)->nullable();
            $table->foreignId('last_event_id')->nullable()->constrained('google_calendar_sync_events')->nullOnDelete();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_meta')->nullable();
            $table->timestamps();

            $table->unique(['calendar_id', 'google_event_id'], 'gcal_event_maps_calendar_event_unique');
            $table->index(['branch_id', 'updated_at'], 'gcal_event_maps_branch_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_calendar_event_maps');
    }
};
