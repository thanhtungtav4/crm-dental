<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ExplainOpsHotpaths extends Command
{
    protected $signature = 'reports:explain-ops-hotpaths
        {--branch_id= : Branch scope để tạo plan baseline}
        {--write= : Đường dẫn file JSON baseline}
        {--strict : Fail nếu phát hiện full table scan}';

    protected $description = 'Sinh EXPLAIN baseline cho hot-path queries của care/appointment/finance.';

    public function handle(): int
    {
        $branchId = $this->option('branch_id') !== null
            ? (int) $this->option('branch_id')
            : (int) (Branch::query()->value('id') ?? 0);
        $doctorId = (int) (User::query()->whereNotNull('branch_id')->value('id') ?? 0);
        $now = now();
        $from = $now->copy()->startOfDay()->toDateTimeString();
        $to = $now->copy()->endOfDay()->toDateTimeString();
        $driver = (string) DB::connection()->getDriverName();

        $queries = $this->hotpathQueries(
            branchId: $branchId,
            doctorId: $doctorId,
            from: $from,
            to: $to,
        );

        $results = collect($queries)->map(function (array $query) use ($driver): array {
            $planRows = $this->explain($driver, (string) $query['sql'], (array) $query['bindings']);

            return [
                'key' => (string) $query['key'],
                'sql' => (string) $query['sql'],
                'bindings' => (array) $query['bindings'],
                'plan_rows' => $planRows,
                'full_scan_detected' => $this->hasFullScan($driver, $planRows),
            ];
        })->values();

        $this->table(
            ['Key', 'Plan Rows', 'Full Scan'],
            $results->map(fn (array $row): array => [
                $row['key'],
                count((array) $row['plan_rows']),
                $row['full_scan_detected'] ? 'yes' : 'no',
            ])->all(),
        );

        $baseline = [
            'generated_at' => now()->toIso8601String(),
            'connection' => config('database.default'),
            'driver' => $driver,
            'branch_id' => $branchId,
            'doctor_id' => $doctorId,
            'window' => [
                'from' => $from,
                'to' => $to,
            ],
            'queries' => $results->all(),
        ];

        $target = $this->option('write')
            ? (string) $this->option('write')
            : storage_path('app/reports/explain-hotpaths-'.now()->format('Ymd_His').'.json');
        $this->writeBaseline($target, $baseline);

        $fullScanCount = (int) $results->where('full_scan_detected', true)->count();

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: [
                'command' => 'reports:explain-ops-hotpaths',
                'driver' => $driver,
                'branch_id' => $branchId,
                'doctor_id' => $doctorId,
                'full_scan_count' => $fullScanCount,
                'write' => $target,
            ],
        );

        $this->line('EXPLAIN_BASELINE_WRITTEN: '.$target);
        $this->line('EXPLAIN_FULL_SCAN_COUNT: '.$fullScanCount);

        if ((bool) $this->option('strict') && $fullScanCount > 0) {
            $this->error('Strict mode: phát hiện full table scan trong hot-path query.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{key:string,sql:string,bindings:array<int, mixed>}>
     */
    protected function hotpathQueries(int $branchId, int $doctorId, string $from, string $to): array
    {
        $appointmentStatuses = ['scheduled', 'confirmed', 'in_progress'];
        $invoiceStatuses = ['issued', 'partial', 'overdue'];

        return [
            [
                'key' => 'notes_care_queue',
                'sql' => 'SELECT id FROM notes WHERE care_type = ? AND care_status = ? AND care_at BETWEEN ? AND ? LIMIT 50',
                'bindings' => ['payment_reminder', 'not_started', $from, $to],
            ],
            [
                'key' => 'appointments_capacity',
                'sql' => 'SELECT id FROM appointments WHERE branch_id = ? AND doctor_id = ? AND status IN '.$this->inClause($appointmentStatuses).' AND date BETWEEN ? AND ? LIMIT 50',
                'bindings' => [$branchId, $doctorId, ...$appointmentStatuses, $from, $to],
            ],
            [
                'key' => 'payments_branch_aging',
                'sql' => 'SELECT id FROM payments WHERE branch_id = ? AND direction = ? AND paid_at BETWEEN ? AND ? LIMIT 50',
                'bindings' => [$branchId, 'receipt', $from, $to],
            ],
            [
                'key' => 'invoices_branch_status',
                'sql' => 'SELECT id FROM invoices WHERE branch_id = ? AND status IN '.$this->inClause($invoiceStatuses).' AND issued_at BETWEEN ? AND ? LIMIT 50',
                'bindings' => [$branchId, ...$invoiceStatuses, $from, $to],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, array<string, mixed>>
     */
    protected function explain(string $driver, string $sql, array $bindings): array
    {
        $prefix = $driver === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        return collect(DB::select($prefix.$sql, $bindings))
            ->map(function (object $row): array {
                return collect((array) $row)
                    ->map(function (mixed $value): mixed {
                        if (is_string($value) || is_numeric($value) || is_bool($value) || $value === null) {
                            return $value;
                        }

                        return (string) $value;
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $planRows
     */
    protected function hasFullScan(string $driver, array $planRows): bool
    {
        if ($driver === 'sqlite') {
            return collect($planRows)->contains(function (array $row): bool {
                $detail = strtolower((string) ($row['detail'] ?? ''));

                if (! Str::contains($detail, 'scan ')) {
                    return false;
                }

                return ! Str::contains($detail, 'using index')
                    && ! Str::contains($detail, 'using covering index');
            });
        }

        return collect($planRows)->contains(function (array $row): bool {
            return strtolower((string) ($row['type'] ?? '')) === 'all';
        });
    }

    protected function inClause(array $values): string
    {
        return '('.implode(',', array_fill(0, count($values), '?')).')';
    }

    /**
     * @param  array<string, mixed>  $baseline
     */
    protected function writeBaseline(string $path, array $baseline): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put(
            $path,
            json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
