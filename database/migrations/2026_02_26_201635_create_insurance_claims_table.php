<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('claim_number')->unique();
            $table->string('payer_name')->nullable();
            $table->decimal('amount_claimed', 12, 2)->default(0);
            $table->decimal('amount_approved', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'denied', 'resubmitted', 'paid', 'cancelled'])
                ->default('draft');
            $table->string('denial_reason_code')->nullable();
            $table->text('denial_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
