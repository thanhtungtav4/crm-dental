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
        if (Schema::hasTable('google_calendar_sync_events')) {
            return;
        }

        Schema::create('google_calendar_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('event_type', 20);
            $table->json('payload');
            $table->string('payload_checksum', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->string('external_event_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'gcal_sync_events_status_retry_idx');
            $table->index(['appointment_id', 'created_at'], 'gcal_sync_events_appointment_created_idx');
            $table->index(['payload_checksum'], 'gcal_sync_events_payload_checksum_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('google_calendar_sync_events')) {
            return;
        }

        Schema::dropIfExists('google_calendar_sync_events');
    }
};
