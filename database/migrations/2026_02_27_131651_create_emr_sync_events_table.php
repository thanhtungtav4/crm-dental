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
        Schema::create('emr_sync_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('event_type', 60);
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
            $table->string('external_patient_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'emr_sync_events_status_retry_idx');
            $table->index(['patient_id', 'created_at'], 'emr_sync_events_patient_created_idx');
            $table->index(['payload_checksum'], 'emr_sync_events_payload_checksum_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emr_sync_events');
    }
};
