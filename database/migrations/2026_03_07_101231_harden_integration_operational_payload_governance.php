<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $tableColumns = [
        'web_lead_ingestions' => [
            'payload' => true,
            'response' => true,
        ],
        'zalo_webhook_events' => [
            'payload' => true,
        ],
        'emr_sync_events' => [
            'payload' => false,
        ],
        'emr_sync_logs' => [
            'request_payload' => true,
            'response_payload' => true,
        ],
        'google_calendar_sync_events' => [
            'payload' => false,
        ],
        'google_calendar_sync_logs' => [
            'request_payload' => true,
            'response_payload' => true,
        ],
    ];

    public function up(): void
    {
        foreach ($this->tableColumns as $table => $columns) {
            $this->hardenTable($table, $columns);
        }
    }

    public function down(): void
    {
        foreach ($this->tableColumns as $table => $columns) {
            $this->restoreTable($table, $columns);
        }
    }

    /**
     * @param  array<string, bool>  $columns
     */
    protected function hardenTable(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column => $nullable) {
            $this->modifyColumn($table, $column, sprintf('LONGTEXT %s', $nullable ? 'NULL' : 'NOT NULL'));
        }

        DB::table($table)
            ->select(array_merge(['id'], array_keys($columns)))
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column => $nullable) {
                        $decoded = $this->decodeNullableArray($row->{$column});
                        if (! $nullable && $decoded === null) {
                            $decoded = [];
                        }

                        $updates[$column] = $this->encryptNullableArray($decoded, $nullable);
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });
    }

    /**
     * @param  array<string, bool>  $columns
     */
    protected function restoreTable(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->select(array_merge(['id'], array_keys($columns)))
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column => $nullable) {
                        $decoded = $this->decryptNullableArrayIfNeeded($row->{$column});
                        if (! $nullable && $decoded === null) {
                            $decoded = [];
                        }

                        $updates[$column] = $this->encodeNullableArray($decoded, $nullable);
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });

        foreach ($columns as $column => $nullable) {
            $this->modifyColumn($table, $column, sprintf('JSON %s', $nullable ? 'NULL' : 'NOT NULL'));
        }
    }

    protected function modifyColumn(string $table, string $column, string $definition): void
    {
        if (! Schema::hasColumn($table, $column) || $this->isSqlite()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s',
            $table,
            $column,
            $definition,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeNullableArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decryptNullableArrayIfNeeded(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $value), true);

            return is_array($decoded) ? $decoded : null;
        } catch (DecryptException) {
            return $this->decodeNullableArray($value);
        }
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function encryptNullableArray(?array $value, bool $nullable): ?string
    {
        if ($value === null || $value === []) {
            return $nullable ? null : Crypt::encryptString('{}');
        }

        return Crypt::encryptString(
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function encodeNullableArray(?array $value, bool $nullable): ?string
    {
        if ($value === null || $value === []) {
            return $nullable ? null : '{}';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ($nullable ? null : '{}');
    }

    protected function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
