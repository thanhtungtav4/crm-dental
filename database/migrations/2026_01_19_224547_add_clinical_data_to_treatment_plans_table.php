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
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->json('general_exam_data')->nullable()->after('priority'); // Blood pressure, pulse, etc.
            $table->json('tooth_diagnosis_data')->nullable()->after('general_exam_data'); // Snapshot of tooth conditions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            //
        });
    }
};
