<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'patients_customer_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        DB::transaction(function (): void {
            $duplicateCustomerIds = DB::table('patients')
                ->whereNotNull('customer_id')
                ->select('customer_id')
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('customer_id');

            foreach ($duplicateCustomerIds as $customerId) {
                $patientIds = DB::table('patients')
                    ->where('customer_id', $customerId)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->pluck('id');

                if ($patientIds->count() <= 1) {
                    continue;
                }

                $patientIds->shift(); // Keep oldest record and detach the rest.

                DB::table('patients')
                    ->whereIn('id', $patientIds->all())
                    ->update([
                        'customer_id' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        if (! $this->indexExists('patients', self::UNIQUE_INDEX)) {
            Schema::table('patients', function (Blueprint $table): void {
                $table->unique('customer_id', self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        if ($this->indexExists('patients', self::UNIQUE_INDEX)) {
            Schema::table('patients', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'SELECT COUNT(*) as aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$table, $indexName]
            );

            return (int) ($result->aggregate ?? 0) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT COUNT(*) as aggregate FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return (int) ($result->aggregate ?? 0) > 0;
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->pluck('name')
                ->contains($indexName);
        }

        return false;
    }
};
