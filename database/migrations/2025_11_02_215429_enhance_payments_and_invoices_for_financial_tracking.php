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
        // Enhance payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_ref')->nullable()->after('method'); // Bank/card transaction reference
            $table->enum('payment_source', ['patient', 'insurance', 'other'])->default('patient')->after('transaction_ref');
            $table->string('insurance_claim_number')->nullable()->after('payment_source');
        });

        // Enhance invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('qr_code')->nullable()->after('total_amount'); // QR code for bank transfer
            $table->decimal('paid_amount', 12, 2)->default(0)->after('qr_code'); // Track total paid
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_ref', 'payment_source', 'insurance_claim_number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['qr_code', 'paid_amount']);
        });
    }
};
