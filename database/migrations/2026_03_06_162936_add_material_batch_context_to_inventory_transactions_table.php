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
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('material_batch_id')
                ->nullable()
                ->after('material_id')
                ->constrained('material_batches')
                ->nullOnDelete();

            $table->index(['material_id', 'material_batch_id', 'created_at'], 'inventory_transactions_material_batch_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropIndex('inventory_transactions_material_batch_created_idx');
            $table->dropConstrainedForeignId('material_batch_id');
        });
    }
};
