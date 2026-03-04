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
        if (Schema::hasTable('zns_automation_logs')) {
            return;
        }

        Schema::create('zns_automation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zns_automation_event_id')
                ->constrained('zns_automation_events')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(
                ['zns_automation_event_id', 'attempted_at'],
                'zns_auto_logs_event_attempted_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('zns_automation_logs')) {
            return;
        }

        Schema::dropIfExists('zns_automation_logs');
    }
};
