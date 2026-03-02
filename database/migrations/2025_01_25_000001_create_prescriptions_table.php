<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('treatment_session_id')->nullable();
            $table->string('prescription_code')->unique();
            $table->string('prescription_name')->nullable();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->date('treatment_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'treatment_date']);
            $table->index('prescription_code');

            if (Schema::hasTable('patients')) {
                $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            }

            if (Schema::hasTable('treatment_sessions')) {
                $table->foreign('treatment_session_id')->references('id')->on('treatment_sessions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
