<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_issue_items', function (Blueprint $table): void {
            $table->foreignId('material_batch_id')
                ->nullable()
                ->after('material_id')
                ->constrained('material_batches')
                ->restrictOnDelete();

            $table->index(
                ['material_issue_note_id', 'material_batch_id'],
                'material_issue_items_note_batch_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('material_issue_items', function (Blueprint $table): void {
            $table->dropIndex('material_issue_items_note_batch_idx');
            $table->dropConstrainedForeignId('material_batch_id');
        });
    }
};
