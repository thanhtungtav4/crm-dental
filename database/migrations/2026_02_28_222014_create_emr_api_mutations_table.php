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
        Schema::create('emr_api_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 120)->unique();
            $table->string('endpoint', 160);
            $table->string('mutation_type', 80);
            $table->string('payload_checksum', 64);
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('clinical_note_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_payload')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at'], 'emr_api_mutations_patient_idx');
            $table->index(['clinical_note_id', 'created_at'], 'emr_api_mutations_note_idx');
            $table->index(['mutation_type', 'created_at'], 'emr_api_mutations_mutation_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emr_api_mutations');
    }
};
