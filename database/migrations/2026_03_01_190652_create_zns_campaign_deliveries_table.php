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
        if (Schema::hasTable('zns_campaign_deliveries')) {
            return;
        }

        Schema::create('zns_campaign_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zns_campaign_id')->constrained('zns_campaigns')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone', 32);
            $table->string('idempotency_key', 96)->unique();
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['zns_campaign_id', 'status'], 'zns_campaign_deliveries_status_idx');
            $table->index('patient_id');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zns_campaign_deliveries');
    }
};
