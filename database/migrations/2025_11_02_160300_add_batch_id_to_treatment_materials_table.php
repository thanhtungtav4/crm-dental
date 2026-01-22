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
        Schema::table('treatment_materials', function (Blueprint $table) {
            $table->foreignId('batch_id')
                ->nullable()
                ->after('material_id')
                ->constrained('material_batches')
                ->nullOnDelete()
                ->comment('Lô vật tư được sử dụng (để truy xuất nguồn gốc)');
            
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_materials', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
