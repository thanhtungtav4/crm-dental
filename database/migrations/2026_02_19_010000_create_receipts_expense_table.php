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
        Schema::create('receipts_expense', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('voucher_code')->nullable();
            $table->enum('voucher_type', ['receipt', 'expense'])->default('receipt');
            $table->date('voucher_date');
            $table->string('group_code')->nullable();
            $table->string('category_code')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'other'])->default('cash');
            $table->string('payer_or_receiver')->nullable();
            $table->text('content')->nullable();
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['voucher_type', 'status']);
            $table->index('voucher_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts_expense');
    }
};
