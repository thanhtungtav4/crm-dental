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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone_normalized', 32)->nullable()->after('phone');
            $table->string('source_detail', 64)->nullable()->after('source');
            $table->timestamp('last_web_contact_at')->nullable()->after('last_contacted_at');

            $table->index('phone_normalized', 'customers_phone_normalized_idx');
            $table->index('last_web_contact_at', 'customers_last_web_contact_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_phone_normalized_idx');
            $table->dropIndex('customers_last_web_contact_at_idx');
            $table->dropColumn([
                'phone_normalized',
                'source_detail',
                'last_web_contact_at',
            ]);
        });
    }
};
