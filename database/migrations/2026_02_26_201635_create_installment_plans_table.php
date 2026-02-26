<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('plan_code')->unique();
            $table->decimal('financed_amount', 12, 2);
            $table->decimal('down_payment_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2);
            $table->unsignedSmallInteger('number_of_installments')->default(1);
            $table->decimal('installment_amount', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('next_due_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
            $table->json('schedule')->nullable();
            $table->unsignedTinyInteger('dunning_level')->default(0);
            $table->timestamp('last_dunned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('invoice_id');
            $table->index(['status', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
