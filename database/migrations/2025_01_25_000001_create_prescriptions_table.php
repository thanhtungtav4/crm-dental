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
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_session_id')->nullable()->constrained()->nullOnDelete();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
