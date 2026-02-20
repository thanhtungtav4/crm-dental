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
        Schema::create('identification_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('action_type', ['detection', 'merge', 'verification', 'rollback']);
            $table->string('record_type'); // Customer or Patient
            $table->unsignedBigInteger('record_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details'); // Action-specific details
            $table->string('ip_address', 45)->nullable(); // Support IPv6
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at'); // Only created_at, no updated_at

            // Indexes for performance and compliance reporting
            $table->index(['record_type', 'record_id']);
            $table->index('action_type');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identification_logs');
    }
};