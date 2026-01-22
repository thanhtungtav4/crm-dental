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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_id')->nullable()->constrained('treatment_sessions')->nullOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('invoice_no')->nullable()->unique();
            $table->decimal('total_amount', 12,2)->default(0);
            $table->enum('status',['draft','issued','partial','paid','cancelled'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
