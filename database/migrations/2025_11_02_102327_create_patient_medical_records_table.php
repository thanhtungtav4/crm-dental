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
        Schema::create('patient_medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            
            // Allergies & Medical Conditions (CRITICAL for safety)
            $table->json('allergies')->nullable()->comment('Array of allergy names: ["penicillin", "latex", "iodine"]');
            $table->json('chronic_diseases')->nullable()->comment('Array of diseases: ["diabetes", "hypertension", "heart_disease"]');
            $table->json('current_medications')->nullable()->comment('Array of {name, dosage, frequency}');
            
            // Insurance Information
            $table->string('insurance_provider')->nullable()->comment('BHYT / Bảo hiểm tư nhân');
            $table->string('insurance_number')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            
            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable()->comment('Vợ/Chồng/Con/Bố/Mẹ/Anh/Chị');
            
            // Medical Details
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'])->default('unknown');
            $table->text('additional_notes')->nullable()->comment('Other medical information');
            
            // Tracking
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_medical_records');
    }
};
