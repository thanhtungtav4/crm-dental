<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_loyalties')) {
            return;
        }

        Schema::create('patient_loyalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('referral_code', 32)->unique();
            $table->string('referral_code_used', 32)->nullable()->index();
            $table->unsignedBigInteger('referred_by_patient_id')->nullable()->index();
            $table->dateTime('referred_at')->nullable();
            $table->string('tier', 20)->default('bronze')->index();
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points_earned')->default(0);
            $table->integer('lifetime_points_redeemed')->default(0);
            $table->decimal('lifetime_revenue', 14, 2)->default(0);
            $table->dateTime('last_reactivation_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('patient_id', 'patient_loyalties_patient_unique');
            $table->foreign('referred_by_patient_id', 'patient_loyalties_referred_by_foreign')
                ->references('id')
                ->on('patients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patient_loyalties')) {
            return;
        }

        Schema::table('patient_loyalties', function (Blueprint $table): void {
            if (Schema::hasColumn('patient_loyalties', 'referred_by_patient_id')) {
                $table->dropForeign('patient_loyalties_referred_by_foreign');
            }
        });

        Schema::dropIfExists('patient_loyalties');
    }
};
