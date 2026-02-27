<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('duplicate_detections');
        Schema::dropIfExists('record_merges');
        Schema::dropIfExists('identification_logs');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('payment_reminders');
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointment_reminders')) {
            Schema::create('appointment_reminders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['sms', 'email', 'call'])->default('sms');
                $table->dateTime('scheduled_at');
                $table->dateTime('sent_at')->nullable();
                $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
                $table->text('message')->nullable();
                $table->string('recipient')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'status']);
                $table->index(['scheduled_at', 'status']);
                $table->index('sent_at');
            });
        }

        if (! Schema::hasTable('customer_interactions')) {
            Schema::create('customer_interactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('type', [
                    'call',
                    'sms',
                    'email',
                    'facebook',
                    'zalo',
                    'meeting',
                    'appointment',
                    'follow_up',
                    'other',
                ])->default('call');
                $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
                $table->string('subject')->nullable();
                $table->text('description')->nullable();
                $table->enum('outcome', [
                    'successful',
                    'no_answer',
                    'busy',
                    'voicemail',
                    'interested',
                    'not_interested',
                    'callback',
                    'scheduled',
                    'other',
                ])->nullable();
                $table->dateTime('interacted_at')->nullable();
                $table->integer('duration')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['customer_id', 'interacted_at']);
                $table->index(['type', 'interacted_at']);
                $table->index('outcome');
            });
        }

        if (! Schema::hasTable('duplicate_detections')) {
            Schema::create('duplicate_detections', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->string('potential_match_type');
                $table->unsignedBigInteger('potential_match_id');
                $table->decimal('confidence_score', 5, 2);
                $table->json('matching_criteria');
                $table->enum('status', ['pending', 'reviewed', 'merged', 'dismissed'])->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['source_type', 'source_id'], 'idx_source');
                $table->index(['potential_match_type', 'potential_match_id'], 'idx_match');
                $table->index('confidence_score', 'idx_confidence');
                $table->index('status', 'idx_status');
                $table->unique(
                    ['source_type', 'source_id', 'potential_match_type', 'potential_match_id'],
                    'unique_duplicate_detection',
                );
            });
        }

        if (! Schema::hasTable('record_merges')) {
            Schema::create('record_merges', function (Blueprint $table): void {
                $table->id();
                $table->string('primary_record_type');
                $table->unsignedBigInteger('primary_record_id');
                $table->string('merged_record_type');
                $table->unsignedBigInteger('merged_record_id');
                $table->json('merge_data');
                $table->foreignId('performed_by')->constrained('users');
                $table->timestamp('performed_at');
                $table->json('rollback_data')->nullable();
                $table->timestamps();

                $table->index(['primary_record_type', 'primary_record_id']);
                $table->index(['merged_record_type', 'merged_record_id']);
                $table->index('performed_by');
                $table->index('performed_at');
            });
        }

        if (! Schema::hasTable('identification_logs')) {
            Schema::create('identification_logs', function (Blueprint $table): void {
                $table->id();
                $table->enum('action_type', ['detection', 'merge', 'verification', 'rollback']);
                $table->string('record_type');
                $table->unsignedBigInteger('record_id');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('details');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at');

                $table->index(['record_type', 'record_id']);
                $table->index('action_type');
                $table->index('user_id');
                $table->index('created_at');
                $table->index('ip_address');
            });
        }

        if (! Schema::hasTable('installment_plans')) {
            Schema::create('installment_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->decimal('total_amount', 12, 2);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('remaining_amount', 12, 2);
                $table->integer('number_of_installments');
                $table->decimal('installment_amount', 12, 2);
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->enum('payment_frequency', ['monthly', 'weekly', 'custom'])->default('monthly');
                $table->json('schedule')->nullable();
                $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_reminders')) {
            Schema::create('payment_reminders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->enum('reminder_type', ['first', 'second', 'final'])->default('first');
                $table->date('due_date');
                $table->timestamp('sent_at')->nullable();
                $table->enum('delivery_method', ['email', 'sms', 'both', 'notification'])->default('notification');
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->text('message')->nullable();
                $table->timestamps();
            });
        }
    }
};
