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
        Schema::create('web_lead_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 120)->unique();
            $table->string('source', 32)->default('website');
            $table->string('full_name');
            $table->string('phone', 32);
            $table->string('phone_normalized', 32)->nullable();
            $table->string('branch_code', 64)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['phone_normalized', 'created_at'], 'web_lead_ingestions_phone_idx');
            $table->index(['status', 'created_at'], 'web_lead_ingestions_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_lead_ingestions', function (Blueprint $table): void {
            $table->dropIndex('web_lead_ingestions_phone_idx');
            $table->dropIndex('web_lead_ingestions_status_idx');
        });

        Schema::dropIfExists('web_lead_ingestions');
    }
};
