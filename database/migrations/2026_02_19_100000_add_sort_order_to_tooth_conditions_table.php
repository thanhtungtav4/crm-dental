<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tooth_conditions', 'sort_order')) {
            Schema::table('tooth_conditions', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tooth_conditions', 'sort_order')) {
            Schema::table('tooth_conditions', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};

