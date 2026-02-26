<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recall_rules')) {
            return;
        }

        Schema::create('recall_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('name', 255)->nullable();
            $table->unsignedSmallInteger('offset_days')->default(180);
            $table->string('care_channel', 50)->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'branch_id', 'service_id'], 'recall_rules_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_rules');
    }
};
