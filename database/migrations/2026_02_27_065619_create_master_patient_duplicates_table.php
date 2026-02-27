<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_patient_duplicates')) {
            return;
        }

        Schema::create('master_patient_duplicates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('identity_type', 20)->index();
            $table->string('identity_hash', 64)->index();
            $table->string('identity_value')->nullable();
            $table->json('matched_patient_ids');
            $table->json('matched_branch_ids')->nullable();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open')->index();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['identity_type', 'identity_hash', 'status'],
                'master_patient_duplicates_unique_identity_status'
            );
            $table->index(['status', 'confidence_score'], 'master_patient_duplicates_status_confidence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_patient_duplicates');
    }
};
