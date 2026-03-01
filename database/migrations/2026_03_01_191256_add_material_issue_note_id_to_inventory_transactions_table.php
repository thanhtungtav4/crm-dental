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
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_transactions', 'material_issue_note_id')) {
                $table->foreignId('material_issue_note_id')
                    ->nullable()
                    ->after('treatment_session_id')
                    ->constrained('material_issue_notes')
                    ->nullOnDelete();
                $table->index('material_issue_note_id', 'inventory_transactions_issue_note_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_transactions', 'material_issue_note_id')) {
                $table->dropIndex('inventory_transactions_issue_note_idx');
                $table->dropConstrainedForeignId('material_issue_note_id');
            }
        });
    }
};
