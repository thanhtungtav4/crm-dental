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
        Schema::create('duplicate_detections', function (Blueprint $table) {
            $table->id();
            $table->string('source_type'); // Customer or Patient
            $table->unsignedBigInteger('source_id');
            $table->string('potential_match_type'); // Customer or Patient
            $table->unsignedBigInteger('potential_match_id');
            $table->decimal('confidence_score', 5, 2); // 0.00 to 100.00
            $table->json('matching_criteria'); // Details about what matched
            $table->enum('status', ['pending', 'reviewed', 'merged', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['source_type', 'source_id'], 'idx_source');
            $table->index(['potential_match_type', 'potential_match_id'], 'idx_match');
            $table->index('confidence_score', 'idx_confidence');
            $table->index('status', 'idx_status');
            
            // Prevent duplicate detection records
            $table->unique([
                'source_type', 'source_id', 
                'potential_match_type', 'potential_match_id'
            ], 'unique_duplicate_detection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_detections');
    }
};