<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'method')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `payments`
            MODIFY `method` ENUM('cash', 'card', 'transfer', 'vnpay', 'other')
            NOT NULL DEFAULT 'cash'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'method')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payments')->where('method', 'vnpay')->update(['method' => 'transfer']);

        DB::statement("
            ALTER TABLE `payments`
            MODIFY `method` ENUM('cash', 'card', 'transfer', 'other')
            NOT NULL DEFAULT 'cash'
        ");
    }
};

