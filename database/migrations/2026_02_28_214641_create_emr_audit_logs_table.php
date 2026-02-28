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
        Schema::create('emr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action', 80);
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'emr_audit_logs_entity_idx');
            $table->index(['patient_id', 'occurred_at'], 'emr_audit_logs_patient_idx');
            $table->index(['visit_episode_id', 'occurred_at'], 'emr_audit_logs_encounter_idx');
            $table->index(['branch_id', 'occurred_at'], 'emr_audit_logs_branch_idx');
            $table->index(['action', 'occurred_at'], 'emr_audit_logs_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emr_audit_logs');
    }
};
