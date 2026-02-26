<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_snapshots')) {
            return;
        }

        Schema::create('report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_key', 120)->index();
            $table->date('snapshot_date')->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('status', ['success', 'failed'])->default('success')->index();
            $table->enum('sla_status', ['on_time', 'late', 'stale', 'missing'])->default('on_time')->index();
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->json('payload');
            $table->json('lineage')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['snapshot_key', 'snapshot_date', 'branch_id'], 'report_snapshots_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
