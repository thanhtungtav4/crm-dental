<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_patient_identities')) {
            return;
        }

        Schema::create('master_patient_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('identity_type', 20)->index();
            $table->string('identity_hash', 64)->index();
            $table->string('identity_value', 255);
            $table->boolean('is_primary')->default(false);
            $table->decimal('confidence_score', 5, 2)->default(100);
            $table->timestamps();

            $table->unique(
                ['patient_id', 'identity_type', 'identity_hash'],
                'master_patient_identities_unique_patient_identity'
            );
            $table->index(['identity_type', 'identity_hash', 'branch_id'], 'master_patient_identities_detect_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_patient_identities');
    }
};
