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
        Schema::create('zalo_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_fingerprint', 128)->unique();
            $table->string('event_name', 120)->nullable();
            $table->string('event_id', 120)->nullable();
            $table->string('oa_id', 120)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_name', 'received_at'], 'zalo_webhook_events_name_received_idx');
            $table->index(['oa_id', 'received_at'], 'zalo_webhook_events_oa_received_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zalo_webhook_events');
    }
};
