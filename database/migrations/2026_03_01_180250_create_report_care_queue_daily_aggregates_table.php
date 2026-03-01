<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_care_queue_daily_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('branch_scope_id')->default(0);
            $table->string('care_type', 100);
            $table->string('care_type_label', 255)->nullable();
            $table->string('care_status', 50);
            $table->string('care_status_label', 255)->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->dateTime('latest_care_at')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['snapshot_date', 'branch_scope_id', 'care_type', 'care_status'],
                'report_care_queue_daily_aggregates_unique_scope',
            );
            $table->index(
                ['branch_scope_id', 'snapshot_date'],
                'report_care_queue_daily_aggregates_scope_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_care_queue_daily_aggregates');
    }
};
