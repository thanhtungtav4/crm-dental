<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_revenue_daily_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('branch_scope_id')->default(0);
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('service_name', 255);
            $table->string('category_name', 255)->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['snapshot_date', 'branch_scope_id', 'service_id'],
                'report_revenue_daily_aggregates_unique_scope',
            );
            $table->index(
                ['branch_scope_id', 'snapshot_date'],
                'report_revenue_daily_aggregates_scope_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_revenue_daily_aggregates');
    }
};
