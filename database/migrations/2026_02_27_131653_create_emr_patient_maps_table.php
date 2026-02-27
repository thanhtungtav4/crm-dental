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
        Schema::create('emr_patient_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->string('emr_patient_id')->index();
            $table->string('payload_checksum', 64)->nullable();
            $table->foreignId('last_event_id')->nullable()->constrained('emr_sync_events')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emr_patient_maps');
    }
};
