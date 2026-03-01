<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts_expense', function (Blueprint $table): void {
            if (! Schema::hasColumn('receipts_expense', 'patient_id')) {
                $table->foreignId('patient_id')
                    ->nullable()
                    ->after('clinic_id')
                    ->constrained('patients')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('receipts_expense', 'invoice_id')) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('invoices')
                    ->nullOnDelete();
            }

        });
    }

    public function down(): void
    {
        Schema::table('receipts_expense', function (Blueprint $table): void {
            if (Schema::hasColumn('receipts_expense', 'invoice_id')) {
                $table->dropConstrainedForeignId('invoice_id');
            }

            if (Schema::hasColumn('receipts_expense', 'patient_id')) {
                $table->dropConstrainedForeignId('patient_id');
            }
        });
    }
};
