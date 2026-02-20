<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_tooth_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 5); // "11", "21", "55", etc.
            $table->foreignId('tooth_condition_id')->constrained()->cascadeOnDelete();
            $table->enum('treatment_status', ['current', 'in_treatment', 'completed'])->default('current');
            $table->foreignId('treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('diagnosed_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->foreignId('diagnosed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'tooth_number']);
            $table->index(['patient_id', 'treatment_status']);
            $table->unique(['patient_id', 'tooth_number', 'tooth_condition_id'], 'unique_tooth_condition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_tooth_conditions');
    }
};
