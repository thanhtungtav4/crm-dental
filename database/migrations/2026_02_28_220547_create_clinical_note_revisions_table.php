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
        Schema::create('clinical_note_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_note_id')->constrained('clinical_notes')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('operation', 40)->default('update');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('previous_payload')->nullable();
            $table->json('current_payload');
            $table->json('changed_fields')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['clinical_note_id', 'version'], 'clinical_note_revisions_unique_version');
            $table->index(['patient_id', 'created_at'], 'clinical_note_revisions_patient_idx');
            $table->index(['visit_episode_id', 'created_at'], 'clinical_note_revisions_encounter_idx');
            $table->index(['operation', 'created_at'], 'clinical_note_revisions_operation_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_note_revisions');
    }
};
