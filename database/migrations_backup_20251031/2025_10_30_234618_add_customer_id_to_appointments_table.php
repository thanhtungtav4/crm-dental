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
        Schema::table('appointments', function (Blueprint $table) {
            // Thêm customer_id cho trường hợp appointment cho Lead
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            
            // patient_id nullable vì ban đầu là Lead, chưa có Patient
            $table->foreignId('patient_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            
            // Khôi phục patient_id bắt buộc
            $table->foreignId('patient_id')->nullable(false)->change();
        });
    }
};
