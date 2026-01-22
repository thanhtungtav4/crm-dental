<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->foreignId('examining_doctor_id')->nullable()->after('doctor_id')->constrained('users')->nullOnDelete();
            $table->foreignId('treating_doctor_id')->nullable()->after('examining_doctor_id')->constrained('users')->nullOnDelete();
            $table->text('general_exam_notes')->nullable()->after('examination_note');
            $table->json('indication_images')->nullable()->after('indications');
            $table->json('tooth_diagnosis_data')->nullable()->after('indication_images');
            $table->text('other_diagnosis')->nullable()->after('tooth_diagnosis_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->dropForeign(['examining_doctor_id']);
            $table->dropForeign(['treating_doctor_id']);
            $table->dropColumn([
                'examining_doctor_id',
                'treating_doctor_id',
                'general_exam_notes',
                'indication_images',
                'tooth_diagnosis_data',
                'other_diagnosis',
            ]);
        });
    }
};
