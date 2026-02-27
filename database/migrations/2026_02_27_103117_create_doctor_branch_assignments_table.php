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
        if (Schema::hasTable('doctor_branch_assignments')) {
            return;
        }

        Schema::create('doctor_branch_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->date('assigned_from')->nullable();
            $table->date('assigned_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id'], 'doctor_branch_assignments_unique_user_branch');
            $table->index(['branch_id', 'is_active'], 'doctor_branch_assignments_branch_active_index');
            $table->index(['user_id', 'is_active'], 'doctor_branch_assignments_user_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_branch_assignments');
    }
};
