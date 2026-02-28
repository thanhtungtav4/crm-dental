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
        Schema::create('clinical_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_order_id')->constrained('clinical_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('result_code', 32)->unique();
            $table->string('status', 30)->default('draft');
            $table->dateTime('resulted_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->json('payload')->nullable();
            $table->text('interpretation')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinical_order_id', 'status'], 'clinical_results_order_status_idx');
            $table->index(['patient_id', 'status', 'resulted_at'], 'clinical_results_patient_status_resulted_idx');
            $table->index(['branch_id', 'resulted_at'], 'clinical_results_branch_resulted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_results');
    }
};
