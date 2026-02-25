<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeStatuses('scheduled', [
            'pending',
            'booked',
            'new',
            'scheduled',
        ]);

        $this->normalizeStatuses('confirmed', [
            'confirmed',
            'arrived',
        ]);

        $this->normalizeStatuses('in_progress', [
            'in_progress',
            'in-progress',
            'examining',
            'in_treatment',
            'in-treatment',
            'in treatment',
        ]);

        $this->normalizeStatuses('completed', [
            'done',
            'finished',
            'completed',
        ]);

        $this->normalizeStatuses('cancelled', [
            'cancel',
            'canceled',
            'cancelled',
        ]);

        $this->normalizeStatuses('no_show', [
            'no_show',
            'no-show',
            'no show',
        ]);

        $this->normalizeStatuses('rescheduled', [
            'later',
            'rebooked',
            're-booked',
            're_booked',
            'reschedule',
            'rescheduled',
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

        DB::table('appointments')
            ->whereRaw("LOWER(TRIM(status)) IN ({$placeholders})", $normalizedVariants)
            ->update(['status' => $targetStatus]);
    }
};
