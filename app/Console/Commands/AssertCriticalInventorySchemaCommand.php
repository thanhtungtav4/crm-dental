<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AssertCriticalInventorySchemaCommand extends Command
{
    protected $signature = 'schema:assert-critical-inventory-columns {--database= : Tên DB connection}';

    protected $description = 'Fail nếu thiếu cột/index inventory critical cho batch traceability và SKU invariant.';

    public function handle(): int
    {
        $connection = filled($this->option('database'))
            ? (string) $this->option('database')
            : config('database.default');

        $schema = Schema::connection($connection);
        $issues = [];

        foreach ($this->requiredColumns() as $requirement) {
            $table = $requirement['table'];
            $column = $requirement['column'];

            if (! $schema->hasTable($table)) {
                $issues[] = sprintf('Thiếu bảng `%s`.', $table);

                continue;
            }

            if (! $schema->hasColumn($table, $column)) {
                $issues[] = sprintf('Thiếu cột `%s.%s`.', $table, $column);
            }
        }

        foreach ($this->requiredIndexes() as $requirement) {
            $table = $requirement['table'];

            if (! $schema->hasTable($table)) {
                continue;
            }

            $indexes = $schema->getIndexes($table);

            if (! $this->hasExpectedIndex($indexes, $requirement)) {
                $issues[] = sprintf(
                    'Thiếu index `%s` trên `%s(%s)`.',
                    $requirement['name'],
                    $table,
                    implode(', ', $requirement['columns']),
                );
            }
        }

        if ($issues === []) {
            $this->info('Critical inventory schema gate: OK.');

            return self::SUCCESS;
        }

        $this->error('Critical inventory schema gate: FAIL.');

        foreach ($issues as $issue) {
            $this->line('- '.$issue);
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, array{table:string, column:string}>
     */
    protected function requiredColumns(): array
    {
        return [
            [
                'table' => 'material_issue_items',
                'column' => 'material_batch_id',
            ],
            [
                'table' => 'inventory_transactions',
                'column' => 'material_batch_id',
            ],
        ];
    }

    /**
     * @return array<int, array{name:string, table:string, columns:list<string>, unique:bool}>
     */
    protected function requiredIndexes(): array
    {
        return [
            [
                'name' => 'materials_branch_id_sku_unique',
                'table' => 'materials',
                'columns' => ['branch_id', 'sku'],
                'unique' => true,
            ],
            [
                'name' => 'material_issue_items_note_batch_idx',
                'table' => 'material_issue_items',
                'columns' => ['material_issue_note_id', 'material_batch_id'],
                'unique' => false,
            ],
            [
                'name' => 'inventory_transactions_material_batch_created_idx',
                'table' => 'inventory_transactions',
                'columns' => ['material_id', 'material_batch_id', 'created_at'],
                'unique' => false,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $indexes
     * @param  array{name:string, table:string, columns:list<string>, unique:bool}  $requirement
     */
    protected function hasExpectedIndex(array $indexes, array $requirement): bool
    {
        return collect($indexes)->contains(function (array $index) use ($requirement): bool {
            return (array) ($index['columns'] ?? []) === $requirement['columns']
                && (bool) ($index['unique'] ?? false) === $requirement['unique'];
        });
    }
}
