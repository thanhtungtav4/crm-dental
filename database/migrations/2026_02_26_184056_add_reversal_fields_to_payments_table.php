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
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'reversal_of_id')) {
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('invoice_id');
                $table->index('reversal_of_id', 'payments_reversal_of_id_index');
                $table->foreign('reversal_of_id', 'payments_reversal_of_id_foreign')
                    ->references('id')
                    ->on('payments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'reversed_at')) {
                $table->dateTime('reversed_at')->nullable()->after('refund_reason');
            }

            if (! Schema::hasColumn('payments', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
                $table->foreign('reversed_by', 'payments_reversed_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'reversed_by')) {
                $table->dropForeign('payments_reversed_by_foreign');
                $table->dropColumn('reversed_by');
            }

            if (Schema::hasColumn('payments', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }

            if (Schema::hasColumn('payments', 'reversal_of_id')) {
                $table->dropForeign('payments_reversal_of_id_foreign');
                $table->dropIndex('payments_reversal_of_id_index');
                $table->dropColumn('reversal_of_id');
            }
        });
    }
};
