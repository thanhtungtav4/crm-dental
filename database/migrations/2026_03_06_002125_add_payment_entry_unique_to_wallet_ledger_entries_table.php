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
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->unique(
                ['payment_id', 'entry_type'],
                'wallet_ledger_entries_payment_entry_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->dropUnique('wallet_ledger_entries_payment_entry_unique');
        });
    }
};
