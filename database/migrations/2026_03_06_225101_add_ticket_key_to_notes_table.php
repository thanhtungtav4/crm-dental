<?php

use App\Models\Note;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes') && ! Schema::hasColumn('notes', 'ticket_key')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->string('ticket_key')->nullable()->after('source_id');
            });
        }

        $this->backfillTicketKeys();

        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'ticket_key')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->unique('ticket_key', 'notes_ticket_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'ticket_key')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->dropUnique('notes_ticket_key_unique');
                $table->dropColumn('ticket_key');
            });
        }
    }

    protected function backfillTicketKeys(): void
    {
        if (
            ! Schema::hasTable('notes')
            || ! Schema::hasColumn('notes', 'ticket_key')
            || ! Schema::hasColumn('notes', 'source_type')
            || ! Schema::hasColumn('notes', 'source_id')
            || ! Schema::hasColumn('notes', 'care_type')
        ) {
            return;
        }

        $groups = [];

        DB::table('notes')
            ->select([
                'id',
                'source_type',
                'source_id',
                'care_type',
                'care_at',
                'created_at',
                'updated_at',
            ])
            ->whereNull('deleted_at')
            ->whereNull('ticket_key')
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->whereNotNull('care_type')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows) use (&$groups): void {
                foreach ($rows as $row) {
                    $ticketKey = $this->resolveTicketKey($row);

                    if ($ticketKey === null) {
                        continue;
                    }

                    $groups[$ticketKey][] = $row;
                }
            });

        foreach ($groups as $ticketKey => $rows) {
            usort($rows, function (object $left, object $right): int {
                $leftUpdatedAt = (string) ($left->updated_at ?? $left->created_at ?? '');
                $rightUpdatedAt = (string) ($right->updated_at ?? $right->created_at ?? '');

                if ($leftUpdatedAt !== $rightUpdatedAt) {
                    return $rightUpdatedAt <=> $leftUpdatedAt;
                }

                return (int) $right->id <=> (int) $left->id;
            });

            $canonicalId = (int) $rows[0]->id;

            DB::table('notes')
                ->where('id', $canonicalId)
                ->update(['ticket_key' => $ticketKey]);
        }
    }

    protected function resolveTicketKey(object $row): ?string
    {
        $sourceType = trim((string) ($row->source_type ?? ''));
        $careType = trim((string) ($row->care_type ?? ''));
        $sourceId = $row->source_id;

        if ($sourceType === '' || $careType === '' || ! is_numeric($sourceId)) {
            return null;
        }

        if (! str_starts_with($sourceType, 'App\\Models\\')) {
            return null;
        }

        $scope = null;

        if ($careType === 'birthday_care') {
            $referenceDate = $row->care_at ?: $row->created_at;

            if ($referenceDate === null) {
                return null;
            }

            $scope = date('Y', strtotime((string) $referenceDate));
        }

        return Note::ticketKey($sourceType, (int) $sourceId, $careType, $scope);
    }
};
