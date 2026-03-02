<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AssertCriticalForeignKeys extends Command
{
    protected $signature = 'schema:assert-critical-foreign-keys {--database= : Tên DB connection}';

    protected $description = 'Fail nếu thiếu foreign key critical cho prescriptions và patient_tooth_conditions.';

    public function handle(): int
    {
        $connection = filled($this->option('database'))
            ? (string) $this->option('database')
            : config('database.default');

        $schema = Schema::connection($connection);
        $requirements = $this->requirements();
        $issues = [];

        foreach ($requirements as $requirement) {
            $table = $requirement['table'];

            if (! $schema->hasTable($table)) {
                $issues[] = sprintf('Thiếu bảng `%s`.', $table);

                continue;
            }

            $foreignKeys = $schema->getForeignKeys($table);

            if (! $this->hasExpectedForeignKey($foreignKeys, $requirement)) {
                $issues[] = sprintf(
                    'Thiếu FK `%s.%s -> %s.%s`.',
                    $requirement['table'],
                    $requirement['column'],
                    $requirement['foreign_table'],
                    $requirement['foreign_column'],
                );
            }
        }

        if ($issues === []) {
            $this->info('Critical foreign key gate: OK.');

            return self::SUCCESS;
        }

        $this->error('Critical foreign key gate: FAIL.');

        foreach ($issues as $issue) {
            $this->line('- '.$issue);
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, array{table:string, column:string, foreign_table:string, foreign_column:string}>
     */
    private function requirements(): array
    {
        return [
            [
                'table' => 'prescriptions',
                'column' => 'patient_id',
                'foreign_table' => 'patients',
                'foreign_column' => 'id',
            ],
            [
                'table' => 'prescriptions',
                'column' => 'treatment_session_id',
                'foreign_table' => 'treatment_sessions',
                'foreign_column' => 'id',
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'patient_id',
                'foreign_table' => 'patients',
                'foreign_column' => 'id',
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'tooth_condition_id',
                'foreign_table' => 'tooth_conditions',
                'foreign_column' => 'id',
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'treatment_plan_id',
                'foreign_table' => 'treatment_plans',
                'foreign_column' => 'id',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @param  array{table:string, column:string, foreign_table:string, foreign_column:string}  $requirement
     */
    private function hasExpectedForeignKey(array $foreignKeys, array $requirement): bool
    {
        return collect($foreignKeys)->contains(function (array $foreignKey) use ($requirement): bool {
            return (array) ($foreignKey['columns'] ?? []) === [$requirement['column']]
                && (string) ($foreignKey['foreign_table'] ?? '') === $requirement['foreign_table']
                && (array) ($foreignKey['foreign_columns'] ?? []) === [$requirement['foreign_column']];
        });
    }
}
