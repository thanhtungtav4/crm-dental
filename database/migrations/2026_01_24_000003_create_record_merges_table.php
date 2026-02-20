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
        Schema::create('record_merges', function (Blueprint $table) {
            $table->id();
            $table->string('primary_record_type'); // Customer or Patient
            $table->unsignedBigInteger('primary_record_id');
            $table->string('merged_record_type'); // Customer or Patient
            $table->unsignedBigInteger('merged_record_id');
            $table->json('merge_data'); // Field mappings and merge decisions
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('performed_at');
            $table->json('rollback_data')->nullable(); // Data needed for rollback
            $table->timestamps();

            // Indexes for performance
            $table->index(['primary_record_type', 'primary_record_id']);
            $table->index(['merged_record_type', 'merged_record_id']);
            $table->index('performed_by');
            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_merges');
    }
};