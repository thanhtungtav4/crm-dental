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
        Schema::table('services', function (Blueprint $table) {
            $table->enum('workflow_type', ['manual', 'protocol'])->default('manual')->after('active');
            $table->string('protocol_id')->nullable()->after('workflow_type')->comment('Link to specific protocol implementation');
            $table->integer('vat_rate')->default(0)->after('default_price')->comment('0, 5, 8, 10');
            // 'active' already exists in services table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['workflow_type', 'protocol_id', 'vat_rate']);
        });
    }
};
