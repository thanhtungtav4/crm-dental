<?php

use App\Support\PatientIdentityNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenCampaignDeliveries();
        $this->hardenAutomationEvents();
        $this->hardenAutomationLogs();
    }

    public function down(): void
    {
        $this->restoreCampaignDeliveries();
        $this->restoreAutomationEvents();
        $this->restoreAutomationLogs();
    }

    protected function hardenCampaignDeliveries(): void
    {
        if (! Schema::hasTable('zns_campaign_deliveries')) {
            return;
        }

        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('zns_campaign_deliveries', 'phone_search_hash')) {
                $table->string('phone_search_hash', 64)->nullable()->after('normalized_phone');
            }
        });

        $this->dropIndexIfExists('zns_campaign_deliveries', 'zns_campaign_delivery_campaign_phone_idx');
        $this->modifyColumn('zns_campaign_deliveries', 'phone', 'TEXT NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'normalized_phone', 'TEXT NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'payload', 'LONGTEXT NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'provider_response', 'LONGTEXT NULL');
        $this->addIndexIfMissing(
            'zns_campaign_deliveries',
            'zns_campaign_delivery_campaign_phone_hash_idx',
            'zns_campaign_id, phone_search_hash'
        );

        DB::table('zns_campaign_deliveries')
            ->select(['id', 'phone', 'normalized_phone', 'payload', 'provider_response'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $phone = $this->decryptNullableStringIfNeeded($row->phone);
                    $normalizedPhone = $this->decryptNullableStringIfNeeded($row->normalized_phone);
                    $payload = $this->decodeNullableArray($row->payload);
                    $providerResponse = $this->decodeNullableArray($row->provider_response);

                    DB::table('zns_campaign_deliveries')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullableString($phone),
                            'normalized_phone' => $this->encryptNullableString($normalizedPhone),
                            'phone_search_hash' => $this->phoneSearchHash($normalizedPhone ?: $phone),
                            'payload' => $this->encryptNullableArray($payload),
                            'provider_response' => $this->encryptNullableArray($providerResponse),
                        ]);
                }
            });
    }

    protected function hardenAutomationEvents(): void
    {
        if (! Schema::hasTable('zns_automation_events')) {
            return;
        }

        Schema::table('zns_automation_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('zns_automation_events', 'phone_search_hash')) {
                $table->string('phone_search_hash', 64)->nullable()->after('normalized_phone');
            }
        });

        $this->modifyColumn('zns_automation_events', 'phone', 'TEXT NULL');
        $this->modifyColumn('zns_automation_events', 'normalized_phone', 'TEXT NULL');
        $this->modifyColumn('zns_automation_events', 'payload', 'LONGTEXT NOT NULL');
        $this->modifyColumn('zns_automation_events', 'provider_response', 'LONGTEXT NULL');
        $this->addIndexIfMissing('zns_automation_events', 'zns_auto_events_phone_hash_idx', 'phone_search_hash');

        DB::table('zns_automation_events')
            ->select(['id', 'phone', 'normalized_phone', 'payload', 'provider_response'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $phone = $this->decryptNullableStringIfNeeded($row->phone);
                    $normalizedPhone = $this->decryptNullableStringIfNeeded($row->normalized_phone);
                    $payload = $this->decodeNullableArray($row->payload) ?? [];
                    $providerResponse = $this->decodeNullableArray($row->provider_response);

                    DB::table('zns_automation_events')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullableString($phone),
                            'normalized_phone' => $this->encryptNullableString($normalizedPhone),
                            'phone_search_hash' => $this->phoneSearchHash($normalizedPhone ?: $phone),
                            'payload' => $this->encryptNullableArray($payload),
                            'provider_response' => $this->encryptNullableArray($providerResponse),
                        ]);
                }
            });
    }

    protected function hardenAutomationLogs(): void
    {
        if (! Schema::hasTable('zns_automation_logs')) {
            return;
        }

        $this->modifyColumn('zns_automation_logs', 'request_payload', 'LONGTEXT NULL');
        $this->modifyColumn('zns_automation_logs', 'response_payload', 'LONGTEXT NULL');

        DB::table('zns_automation_logs')
            ->select(['id', 'request_payload', 'response_payload'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('zns_automation_logs')
                        ->where('id', $row->id)
                        ->update([
                            'request_payload' => $this->encryptNullableArray($this->decodeNullableArray($row->request_payload)),
                            'response_payload' => $this->encryptNullableArray($this->decodeNullableArray($row->response_payload)),
                        ]);
                }
            });
    }

    protected function restoreCampaignDeliveries(): void
    {
        if (! Schema::hasTable('zns_campaign_deliveries')) {
            return;
        }

        DB::table('zns_campaign_deliveries')
            ->select(['id', 'phone', 'normalized_phone', 'payload', 'provider_response'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('zns_campaign_deliveries')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->decryptNullableStringIfNeeded($row->phone),
                            'normalized_phone' => $this->decryptNullableStringIfNeeded($row->normalized_phone),
                            'payload' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->payload)),
                            'provider_response' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->provider_response)),
                        ]);
                }
            });

        $this->dropIndexIfExists('zns_campaign_deliveries', 'zns_campaign_delivery_campaign_phone_hash_idx');
        $this->modifyColumn('zns_campaign_deliveries', 'phone', 'VARCHAR(32) NOT NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'normalized_phone', 'VARCHAR(32) NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'payload', 'JSON NULL');
        $this->modifyColumn('zns_campaign_deliveries', 'provider_response', 'JSON NULL');
        $this->addIndexIfMissing(
            'zns_campaign_deliveries',
            'zns_campaign_delivery_campaign_phone_idx',
            'zns_campaign_id, normalized_phone'
        );

        Schema::table('zns_campaign_deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('zns_campaign_deliveries', 'phone_search_hash')) {
                $table->dropColumn('phone_search_hash');
            }
        });
    }

    protected function restoreAutomationEvents(): void
    {
        if (! Schema::hasTable('zns_automation_events')) {
            return;
        }

        DB::table('zns_automation_events')
            ->select(['id', 'phone', 'normalized_phone', 'payload', 'provider_response'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('zns_automation_events')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->decryptNullableStringIfNeeded($row->phone),
                            'normalized_phone' => $this->decryptNullableStringIfNeeded($row->normalized_phone),
                            'payload' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->payload)) ?? '{}',
                            'provider_response' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->provider_response)),
                        ]);
                }
            });

        $this->dropIndexIfExists('zns_automation_events', 'zns_auto_events_phone_hash_idx');
        $this->modifyColumn('zns_automation_events', 'phone', 'VARCHAR(32) NULL');
        $this->modifyColumn('zns_automation_events', 'normalized_phone', 'VARCHAR(32) NULL');
        $this->modifyColumn('zns_automation_events', 'payload', 'JSON NOT NULL');
        $this->modifyColumn('zns_automation_events', 'provider_response', 'JSON NULL');

        Schema::table('zns_automation_events', function (Blueprint $table): void {
            if (Schema::hasColumn('zns_automation_events', 'phone_search_hash')) {
                $table->dropColumn('phone_search_hash');
            }
        });
    }

    protected function restoreAutomationLogs(): void
    {
        if (! Schema::hasTable('zns_automation_logs')) {
            return;
        }

        DB::table('zns_automation_logs')
            ->select(['id', 'request_payload', 'response_payload'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('zns_automation_logs')
                        ->where('id', $row->id)
                        ->update([
                            'request_payload' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->request_payload)),
                            'response_payload' => $this->encodeNullableArray($this->decryptNullableArrayIfNeeded($row->response_payload)),
                        ]);
                }
            });

        $this->modifyColumn('zns_automation_logs', 'request_payload', 'JSON NULL');
        $this->modifyColumn('zns_automation_logs', 'response_payload', 'JSON NULL');
    }

    protected function modifyColumn(string $table, string $column, string $definition): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->isSqlite()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s',
            $table,
            $column,
            $definition,
        ));
    }

    protected function addIndexIfMissing(string $table, string $indexName, string $columns): void
    {
        try {
            if ($this->isSqlite()) {
                DB::statement(sprintf(
                    'CREATE INDEX IF NOT EXISTS "%s" ON "%s" (%s)',
                    $indexName,
                    $table,
                    $columns,
                ));

                return;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
                $table,
                $indexName,
                $columns,
            ));
        } catch (\Throwable) {
        }
    }

    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            if ($this->isSqlite()) {
                DB::statement(sprintf(
                    'DROP INDEX IF EXISTS "%s"',
                    $indexName,
                ));

                return;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                $table,
                $indexName,
            ));
        } catch (\Throwable) {
        }
    }

    protected function encryptNullableString(mixed $value): ?string
    {
        $string = $this->stringOrNull($value);

        return $string === null ? null : Crypt::encryptString($string);
    }

    protected function decryptNullableStringIfNeeded(mixed $value): ?string
    {
        $string = $this->stringOrNull($value);

        if ($string === null) {
            return null;
        }

        try {
            return Crypt::decryptString($string);
        } catch (\Throwable) {
            return $string;
        }
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function encryptNullableArray(?array $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decryptNullableArrayIfNeeded(mixed $value): ?array
    {
        $string = $this->stringOrNull($value);

        if ($string === null) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($string), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return $this->decodeNullableArray($string);
        }
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

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function encodeNullableArray(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function phoneSearchHash(?string $phone): ?string
    {
        $normalized = PatientIdentityNormalizer::normalizePhone($phone);

        return $normalized === null
            ? null
            : hash('sha256', 'zns-phone|'.$normalized);
    }

    protected function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
