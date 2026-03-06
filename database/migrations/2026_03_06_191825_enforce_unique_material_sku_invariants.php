<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('materials')
            ->where('sku', '')
            ->update(['sku' => null]);

        Schema::table('materials', function (Blueprint $table): void {
            $table->unique(['branch_id', 'sku'], 'materials_branch_id_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropUnique('materials_branch_id_sku_unique');
        });
    }
};
