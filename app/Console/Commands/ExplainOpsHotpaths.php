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
        {--benchmark-runs=5 : Số lần chạy benchmark mỗi query để tính p95}
        {--sla-p95-ms=250 : Ngưỡng SLA p95 (ms) cho mỗi hot-path query}
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
        $benchmarkRuns = max(1, (int) $this->option('benchmark-runs'));
        $slaP95Ms = max(0.001, (float) $this->option('sla-p95-ms'));

        $queries = $this->hotpathQueries(
            branchId: $branchId,
            doctorId: $doctorId,
            from: $from,
            to: $to,
        );

        $results = collect($queries)->map(function (array $query) use ($driver, $benchmarkRuns, $slaP95Ms): array {
            $planRows = $this->explain($driver, (string) $query['sql'], (array) $query['bindings']);
            $benchmark = $this->benchmark(
                sql: (string) $query['sql'],
                bindings: (array) $query['bindings'],
                runs: $benchmarkRuns,
            );

            return [
                'key' => (string) $query['key'],
                'sql' => (string) $query['sql'],
                'bindings' => (array) $query['bindings'],
                'plan_rows' => $planRows,
                'full_scan_detected' => $this->hasFullScan($driver, $planRows),
                'benchmark_runs' => $benchmarkRuns,
                'benchmark_ms' => $benchmark['runs_ms'],
                'avg_ms' => $benchmark['avg_ms'],
                'p95_ms' => $benchmark['p95_ms'],
                'max_ms' => $benchmark['max_ms'],
                'sla_p95_ms' => $slaP95Ms,
                'sla_violated' => $benchmark['p95_ms'] > $slaP95Ms,
            ];
        })->values();

        $this->table(
            ['Key', 'Plan Rows', 'Full Scan', 'P95 (ms)', 'SLA'],
            $results->map(fn (array $row): array => [
                $row['key'],
                count((array) $row['plan_rows']),
                $row['full_scan_detected'] ? 'yes' : 'no',
                number_format((float) $row['p95_ms'], 2),
                $row['sla_violated'] ? 'violated' : 'ok',
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
            'benchmark' => [
                'runs' => $benchmarkRuns,
                'sla_p95_ms' => $slaP95Ms,
            ],
            'queries' => $results->all(),
        ];

        $target = $this->option('write')
            ? (string) $this->option('write')
            : storage_path('app/reports/explain-hotpaths-'.now()->format('Ymd_His').'.json');
        $this->writeBaseline($target, $baseline);

        $fullScanCount = (int) $results->where('full_scan_detected', true)->count();
        $slaViolationCount = (int) $results->where('sla_violated', true)->count();

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
                'benchmark_runs' => $benchmarkRuns,
                'sla_p95_ms' => $slaP95Ms,
                'sla_violation_count' => $slaViolationCount,
                'write' => $target,
            ],
        );

        $this->line('EXPLAIN_BASELINE_WRITTEN: '.$target);
        $this->line('EXPLAIN_FULL_SCAN_COUNT: '.$fullScanCount);
        $this->line('EXPLAIN_SLA_VIOLATION_COUNT: '.$slaViolationCount);

        if ((bool) $this->option('strict') && ($fullScanCount > 0 || $slaViolationCount > 0)) {
            if ($fullScanCount > 0) {
                $this->error('Strict mode: phát hiện full table scan trong hot-path query.');
            }

            if ($slaViolationCount > 0) {
                $this->error('Strict mode: phát hiện hot-path query vượt ngưỡng SLA p95.');
            }

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
     * @param  array<int, mixed>  $bindings
     * @return array{
     *     runs_ms:array<int, float>,
     *     avg_ms:float,
     *     p95_ms:float,
     *     max_ms:float
     * }
     */
    protected function benchmark(string $sql, array $bindings, int $runs): array
    {
        $runsMs = [];

        for ($iteration = 0; $iteration < $runs; $iteration++) {
            $startedAt = microtime(true);
            DB::select($sql, $bindings);
            $runsMs[] = round((microtime(true) - $startedAt) * 1000, 3);
        }

        sort($runsMs);

        $count = count($runsMs);
        $p95Index = (int) max(0, min($count - 1, ceil($count * 0.95) - 1));
        $avgMs = $count > 0 ? round(array_sum($runsMs) / $count, 3) : 0.0;
        $maxMs = $count > 0 ? max($runsMs) : 0.0;
        $p95Ms = $count > 0 ? (float) $runsMs[$p95Index] : 0.0;

        return [
            'runs_ms' => $runsMs,
            'avg_ms' => $avgMs,
            'p95_ms' => $p95Ms,
            'max_ms' => $maxMs,
        ];
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
