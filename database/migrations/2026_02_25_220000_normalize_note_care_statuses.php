<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeStatuses('not_started', [
            'planned',
            'pending',
            'not_started',
            'not-started',
            'not started',
        ]);

        $this->normalizeStatuses('in_progress', [
            'in_progress',
            'in-progress',
            'in progress',
        ]);

        $this->normalizeStatuses('done', [
            'completed',
            'done',
        ]);

        $this->normalizeStatuses('need_followup', [
            'no_response',
            'need_followup',
            'need-followup',
            'need followup',
        ]);

        $this->normalizeStatuses('failed', [
            'cancelled',
            'canceled',
            'failed',
        ]);

        DB::table('notes')
            ->whereNull('care_status')
            ->update(['care_status' => 'done']);

        DB::table('notes')
            ->whereNull('care_mode')
            ->update([
                'care_mode' => DB::raw("CASE WHEN care_status = 'not_started' THEN 'scheduled' ELSE 'immediate' END"),
            ]);
    }

    public function down(): void
    {
        // Irreversible normalization migration.
    }

    private function normalizeStatuses(string $targetStatus, array $variants): void
    {
        $normalizedVariants = array_values(array_unique(array_map(
            static fn (string $variant): string => strtolower(trim($variant)),
            $variants,
        )));

        $placeholders = implode(', ', array_fill(0, count($normalizedVariants), '?'));

        DB::table('notes')
            ->whereRaw("LOWER(TRIM(care_status)) IN ({$placeholders})", $normalizedVariants)
            ->update(['care_status' => $targetStatus]);
    }
};
