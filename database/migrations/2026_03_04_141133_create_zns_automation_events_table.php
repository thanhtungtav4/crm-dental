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
        if (Schema::hasTable('zns_automation_events')) {
            return;
        }

        Schema::create('zns_automation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->string('event_type', 50);
            $table->string('template_key', 50);
            $table->string('template_id_snapshot', 255);
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('normalized_phone', 32)->nullable();
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
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status_code', 120)->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'zns_auto_events_status_retry_idx');
            $table->index(['event_type', 'status'], 'zns_auto_events_type_status_idx');
            $table->index(['appointment_id', 'event_type'], 'zns_auto_events_appointment_type_idx');
            $table->index(['customer_id', 'event_type'], 'zns_auto_events_customer_type_idx');
            $table->index(['patient_id', 'event_type'], 'zns_auto_events_patient_type_idx');
            $table->index(['payload_checksum'], 'zns_auto_events_payload_checksum_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('zns_automation_events')) {
            return;
        }

        Schema::dropIfExists('zns_automation_events');
    }
};
