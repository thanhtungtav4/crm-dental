<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_risk_profiles')) {
            return;
        }

        Schema::create('patient_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->date('as_of_date')->index();
            $table->string('model_version', 40)->default('risk_baseline_v1')->index();
            $table->decimal('no_show_risk_score', 5, 2)->default(0);
            $table->decimal('churn_risk_score', 5, 2)->default(0);
            $table->string('risk_level', 20)->default('low')->index();
            $table->string('recommended_action', 255)->nullable();
            $table->dateTime('generated_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('feature_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['patient_id', 'as_of_date', 'model_version'],
                'patient_risk_profiles_unique_snapshot',
            );
            $table->index(['risk_level', 'as_of_date'], 'patient_risk_profiles_level_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_risk_profiles');
    }
};
