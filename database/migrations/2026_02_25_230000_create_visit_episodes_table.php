<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('chair_code', 30)->nullable();
            $table->string('status', 50)->default('scheduled');

            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('in_chair_at')->nullable();
            $table->dateTime('check_out_at')->nullable();

            $table->unsignedInteger('planned_duration_minutes')->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->unsignedInteger('waiting_minutes')->nullable();
            $table->unsignedInteger('chair_minutes')->nullable();
            $table->unsignedInteger('overrun_minutes')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('appointment_id');
            $table->index(['branch_id', 'status']);
            $table->index('scheduled_at');
            $table->index('check_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_episodes');
    }
};
