<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
            $table->foreignId('plan_item_id')->nullable()->constrained('plan_items')->nullOnDelete();
            $table->foreignId('treatment_session_id')->nullable()->constrained('treatment_sessions')->nullOnDelete();
            $table->foreignId('clinical_order_id')->nullable()->constrained('clinical_orders')->nullOnDelete();
            $table->foreignId('clinical_result_id')->nullable()->constrained('clinical_results')->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('consent_id')->nullable()->constrained('consents')->nullOnDelete();

            $table->dateTime('captured_at')->nullable();
            $table->string('modality', 32)->default('photo');
            $table->string('anatomy_scope', 64)->nullable();
            $table->string('phase', 32)->default('unspecified');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('storage_disk', 64)->default('public');
            $table->string('storage_path');
            $table->string('status', 32)->default('active');
            $table->string('retention_class', 40)->default('clinical_operational');
            $table->boolean('legal_hold')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'captured_at'], 'clinical_media_assets_patient_captured_idx');
            $table->index(['branch_id', 'captured_at'], 'clinical_media_assets_branch_captured_idx');
            $table->index(['exam_session_id', 'phase'], 'clinical_media_assets_exam_phase_idx');
            $table->index(['visit_episode_id', 'phase'], 'clinical_media_assets_visit_phase_idx');
            $table->index(['status', 'retention_class'], 'clinical_media_assets_status_retention_idx');
            $table->index('checksum_sha256', 'clinical_media_assets_checksum_idx');
            $table->index(['clinical_order_id', 'clinical_result_id'], 'clinical_media_assets_order_result_idx');
            $table->index('storage_path', 'clinical_media_assets_storage_path_idx');
        });

        Schema::create('clinical_media_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_media_asset_id')->constrained('clinical_media_assets')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->boolean('is_original')->default(false);
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('storage_disk', 64)->default('public');
            $table->string('storage_path');
            $table->json('transform_meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinical_media_asset_id', 'version_number'], 'clinical_media_versions_asset_version_unique');
            $table->index(['clinical_media_asset_id', 'is_original'], 'clinical_media_versions_asset_original_idx');
            $table->index('checksum_sha256', 'clinical_media_versions_checksum_idx');
        });

        Schema::create('clinical_media_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_media_asset_id')->constrained('clinical_media_assets')->cascadeOnDelete();
            $table->foreignId('clinical_media_version_id')->nullable()->constrained('clinical_media_versions')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('purpose', 120)->nullable();
            $table->json('context')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['clinical_media_asset_id', 'occurred_at'], 'clinical_media_access_logs_asset_occurred_idx');
            $table->index(['branch_id', 'occurred_at'], 'clinical_media_access_logs_branch_occurred_idx');
            $table->index(['actor_id', 'occurred_at'], 'clinical_media_access_logs_actor_occurred_idx');
            $table->index(['action', 'occurred_at'], 'clinical_media_access_logs_action_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_media_access_logs');
        Schema::dropIfExists('clinical_media_versions');
        Schema::dropIfExists('clinical_media_assets');
    }
};
