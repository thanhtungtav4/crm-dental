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
        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained('treatment_plans')->cascadeOnDelete();
            $table->foreignId('plan_item_id')->nullable()->constrained()->nullOnDelete(); // optional if you split items
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('procedure')->nullable();
            $table->json('images')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status',['scheduled','done','follow_up'])->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_sessions');
    }
};
