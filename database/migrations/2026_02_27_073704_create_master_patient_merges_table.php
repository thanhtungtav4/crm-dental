<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_patient_merges')) {
            return;
        }

        Schema::create('master_patient_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canonical_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('merged_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('duplicate_case_id')->nullable()->constrained('master_patient_duplicates')->nullOnDelete();
            $table->enum('status', ['applied', 'rolled_back'])->default('applied')->index();
            $table->text('merge_reason')->nullable();
            $table->json('canonical_before')->nullable();
            $table->json('canonical_after')->nullable();
            $table->json('merged_before')->nullable();
            $table->json('merged_after')->nullable();
            $table->json('rewired_record_ids')->nullable();
            $table->json('rewire_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_note')->nullable();
            $table->timestamps();

            $table->index(['canonical_patient_id', 'status'], 'master_patient_merges_canonical_status_index');
            $table->index(['merged_patient_id', 'status'], 'master_patient_merges_merged_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_patient_merges');
    }
};
