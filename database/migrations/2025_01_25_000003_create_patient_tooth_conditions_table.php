<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_tooth_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('tooth_number', 5); // "11", "21", "55", etc.
            $table->unsignedBigInteger('tooth_condition_id');
            $table->enum('treatment_status', ['current', 'in_treatment', 'completed'])->default('current');
            $table->unsignedBigInteger('treatment_plan_id')->nullable();
            $table->text('notes')->nullable();
            $table->date('diagnosed_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->foreignId('diagnosed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'tooth_number']);
            $table->index(['patient_id', 'treatment_status']);
            $table->unique(['patient_id', 'tooth_number', 'tooth_condition_id'], 'unique_tooth_condition');

            if (Schema::hasTable('patients')) {
                $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            }

            if (Schema::hasTable('tooth_conditions')) {
                $table->foreign('tooth_condition_id')->references('id')->on('tooth_conditions')->cascadeOnDelete();
            }

            if (Schema::hasTable('treatment_plans')) {
                $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_tooth_conditions');
    }
};
