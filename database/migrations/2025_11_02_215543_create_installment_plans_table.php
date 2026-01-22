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
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2); // Total amount to be paid
            $table->decimal('paid_amount', 12, 2)->default(0); // Amount paid so far
            $table->decimal('remaining_amount', 12, 2); // Remaining balance (calculated)
            $table->integer('number_of_installments'); // 3, 6, 9, 12 months
            $table->decimal('installment_amount', 12, 2); // Amount per installment
            $table->decimal('interest_rate', 5, 2)->default(0); // Interest rate % (e.g., 0.00 for no interest)
            $table->date('start_date'); // First installment due date
            $table->date('end_date')->nullable(); // Last installment due date
            $table->enum('payment_frequency', ['monthly', 'weekly', 'custom'])->default('monthly');
            $table->json('schedule')->nullable(); // JSON array of installment schedule with due dates
            $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
