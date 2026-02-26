<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_data_sync_logs')) {
            return;
        }

        Schema::create('master_data_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 50)->index();
            $table->foreignId('source_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('target_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('synced_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->enum('status', ['success', 'failed', 'partial'])->default('success')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity', 'source_branch_id', 'target_branch_id'], 'master_data_sync_logs_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_sync_logs');
    }
};
