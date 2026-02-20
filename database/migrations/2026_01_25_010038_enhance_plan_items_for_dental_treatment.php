<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->json('tooth_ids')->nullable()->after('service_id')->comment('Array of tooth numbers e.g. ["18", "17"]');
            $table->json('diagnosis_ids')->nullable()->after('tooth_ids')->comment('Link to patient_tooth_conditions');

            $table->decimal('discount_amount', 12, 2)->default(0)->after('price');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('discount_percent');
            $table->decimal('final_amount', 12, 2)->nullable()->after('vat_amount')->comment('(Qty * Price) - Discount + VAT');

            $table->boolean('is_completed')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn([
                'tooth_ids',
                'diagnosis_ids',
                'discount_amount',
                'discount_percent',
                'vat_amount',
                'final_amount',
                'is_completed'
            ]);
        });
    }
};
