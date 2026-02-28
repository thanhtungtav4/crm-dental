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
        Schema::create('clinical_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_episode_id')->nullable()->constrained('visit_episodes')->nullOnDelete();
            $table->foreignId('clinical_note_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('order_code', 32)->unique();
            $table->string('order_type', 60);
            $table->string('status', 30)->default('pending');
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status', 'requested_at'], 'clinical_orders_patient_status_requested_idx');
            $table->index(['visit_episode_id', 'status'], 'clinical_orders_visit_episode_status_idx');
            $table->index(['branch_id', 'requested_at'], 'clinical_orders_branch_requested_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_orders');
    }
};
