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
        // Thay đổi enum column để thêm 'appointment'
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('walkin','facebook','zalo','referral','appointment','other') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Quay về enum cũ
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('walkin','facebook','zalo','referral','other') NULL");
    }
};
