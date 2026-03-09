<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_lead_email_deliveries')) {
            return;
        }

        Schema::create('web_lead_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('web_lead_ingestion_id')->constrained('web_lead_ingestions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key', 96)->unique();
            $table->enum('recipient_type', ['user', 'mailbox'])->default('user');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->enum('status', ['queued', 'processing', 'sent', 'retryable', 'dead', 'skipped'])->default('queued');
            $table->string('processing_token', 64)->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('manual_resend_count')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('transport_message_id')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('payload')->nullable();
            $table->json('mailer_snapshot')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'web_lead_email_deliveries_status_retry_idx');
            $table->index(['branch_id', 'status'], 'web_lead_email_deliveries_branch_status_idx');
            $table->index(['web_lead_ingestion_id', 'status'], 'web_lead_email_deliveries_ingestion_status_idx');
            $table->index(['recipient_user_id', 'status'], 'web_lead_email_deliveries_recipient_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_lead_email_deliveries');
    }
};
