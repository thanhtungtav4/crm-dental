<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_kpi_alerts')) {
            return;
        }

        Schema::create('operational_kpi_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('report_snapshots')->cascadeOnDelete();
            $table->string('snapshot_key', 120)->index();
            $table->date('snapshot_date')->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('metric_key', 80);
            $table->enum('threshold_direction', ['max', 'min']);
            $table->decimal('threshold_value', 12, 2)->default(0);
            $table->decimal('observed_value', 12, 2)->default(0);
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['new', 'ack', 'resolved'])->default('new')->index();
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'metric_key'], 'operational_kpi_alerts_unique_snapshot_metric');
            $table->index(['status', 'severity'], 'operational_kpi_alerts_status_severity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_kpi_alerts');
    }
};
