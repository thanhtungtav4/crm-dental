<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_loyalty_transactions')) {
            return;
        }

        Schema::create('patient_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_loyalty_id')->constrained('patient_loyalties')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('event_type', 80)->index();
            $table->integer('points_delta');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('source_type', 200)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'patient_loyalty_tx_source_index');
            $table->unique(
                ['patient_loyalty_id', 'event_type', 'source_type', 'source_id'],
                'patient_loyalty_tx_unique_event_source',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_loyalty_transactions');
    }
};
