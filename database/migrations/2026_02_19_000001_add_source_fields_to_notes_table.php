<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasSourceType = Schema::hasColumn('notes', 'source_type');
        $hasSourceId = Schema::hasColumn('notes', 'source_id');

        Schema::table('notes', function (Blueprint $table) use ($hasSourceType, $hasSourceId) {
            if (! $hasSourceType) {
                $table->string('source_type')->nullable()->after('care_at');
            }

            if (! $hasSourceId) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (! $hasSourceType || ! $hasSourceId) {
                $table->index(['source_type', 'source_id'], 'notes_source_index');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('notes')) {
            Schema::table('notes', function (Blueprint $table) {
                if (Schema::hasColumn('notes', 'source_type') && Schema::hasColumn('notes', 'source_id')) {
                    $table->dropIndex('notes_source_index');
                }

                if (Schema::hasColumn('notes', 'source_id')) {
                    $table->dropColumn('source_id');
                }

                if (Schema::hasColumn('notes', 'source_type')) {
                    $table->dropColumn('source_type');
                }
            });
        }
    }
};
