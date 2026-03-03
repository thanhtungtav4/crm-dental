<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_calendar_event_maps') || ! Schema::hasTable('google_calendar_sync_events')) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('google_calendar_event_maps'));
        $hasLastEventForeignKey = $foreignKeys->contains(function (array $foreignKey): bool {
            return (array) ($foreignKey['columns'] ?? []) === ['last_event_id']
                && (string) ($foreignKey['foreign_table'] ?? '') === 'google_calendar_sync_events'
                && (array) ($foreignKey['foreign_columns'] ?? []) === ['id'];
        });

        if ($hasLastEventForeignKey) {
            return;
        }

        Schema::table('google_calendar_event_maps', function (Blueprint $table): void {
            $table->foreign('last_event_id', 'gcal_event_maps_last_event_fk')
                ->references('id')
                ->on('google_calendar_sync_events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_calendar_event_maps')) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('google_calendar_event_maps'));
        $hasLastEventForeignKey = $foreignKeys->contains(function (array $foreignKey): bool {
            return (array) ($foreignKey['columns'] ?? []) === ['last_event_id']
                && (string) ($foreignKey['foreign_table'] ?? '') === 'google_calendar_sync_events'
                && (array) ($foreignKey['foreign_columns'] ?? []) === ['id'];
        });

        if (! $hasLastEventForeignKey) {
            return;
        }

        Schema::table('google_calendar_event_maps', function (Blueprint $table): void {
            $table->dropForeign('gcal_event_maps_last_event_fk');
        });
    }
};
