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
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->enum('reminder_type', ['first', 'second', 'final'])->default('first'); // Escalation level
            $table->date('due_date'); // Original invoice due date
            $table->timestamp('sent_at')->nullable(); // When reminder was sent
            $table->enum('delivery_method', ['email', 'sms', 'both', 'notification'])->default('notification');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('message')->nullable(); // Custom message
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
