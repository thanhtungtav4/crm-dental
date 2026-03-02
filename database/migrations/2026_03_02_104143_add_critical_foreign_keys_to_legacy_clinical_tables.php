<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoOrphansBeforeAddingForeignKeys();

        if (Schema::hasTable('prescriptions') && Schema::hasTable('patients')) {
            if (! $this->hasForeignKey('prescriptions', 'patient_id', 'patients', 'id')) {
                Schema::table('prescriptions', function (Blueprint $table): void {
                    $table->foreign('patient_id', 'prescriptions_patient_id_foreign')
                        ->references('id')
                        ->on('patients')
                        ->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('prescriptions') && Schema::hasTable('treatment_sessions')) {
            if (! $this->hasForeignKey('prescriptions', 'treatment_session_id', 'treatment_sessions', 'id')) {
                Schema::table('prescriptions', function (Blueprint $table): void {
                    $table->foreign('treatment_session_id', 'prescriptions_treatment_session_id_foreign')
                        ->references('id')
                        ->on('treatment_sessions')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('patient_tooth_conditions') && Schema::hasTable('patients')) {
            if (! $this->hasForeignKey('patient_tooth_conditions', 'patient_id', 'patients', 'id')) {
                Schema::table('patient_tooth_conditions', function (Blueprint $table): void {
                    $table->foreign('patient_id', 'patient_tooth_conditions_patient_id_foreign')
                        ->references('id')
                        ->on('patients')
                        ->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('patient_tooth_conditions') && Schema::hasTable('tooth_conditions')) {
            if (! $this->hasForeignKey('patient_tooth_conditions', 'tooth_condition_id', 'tooth_conditions', 'id')) {
                Schema::table('patient_tooth_conditions', function (Blueprint $table): void {
                    $table->foreign('tooth_condition_id', 'patient_tooth_conditions_tooth_condition_id_foreign')
                        ->references('id')
                        ->on('tooth_conditions')
                        ->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('patient_tooth_conditions') && Schema::hasTable('treatment_plans')) {
            if (! $this->hasForeignKey('patient_tooth_conditions', 'treatment_plan_id', 'treatment_plans', 'id')) {
                Schema::table('patient_tooth_conditions', function (Blueprint $table): void {
                    $table->foreign('treatment_plan_id', 'patient_tooth_conditions_treatment_plan_id_foreign')
                        ->references('id')
                        ->on('treatment_plans')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patient_tooth_conditions')) {
            Schema::table('patient_tooth_conditions', function (Blueprint $table): void {
                if ($this->hasForeignKeyByName('patient_tooth_conditions', 'patient_tooth_conditions_treatment_plan_id_foreign')) {
                    $table->dropForeign('patient_tooth_conditions_treatment_plan_id_foreign');
                }

                if ($this->hasForeignKeyByName('patient_tooth_conditions', 'patient_tooth_conditions_tooth_condition_id_foreign')) {
                    $table->dropForeign('patient_tooth_conditions_tooth_condition_id_foreign');
                }

                if ($this->hasForeignKeyByName('patient_tooth_conditions', 'patient_tooth_conditions_patient_id_foreign')) {
                    $table->dropForeign('patient_tooth_conditions_patient_id_foreign');
                }
            });
        }

        if (Schema::hasTable('prescriptions')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                if ($this->hasForeignKeyByName('prescriptions', 'prescriptions_treatment_session_id_foreign')) {
                    $table->dropForeign('prescriptions_treatment_session_id_foreign');
                }

                if ($this->hasForeignKeyByName('prescriptions', 'prescriptions_patient_id_foreign')) {
                    $table->dropForeign('prescriptions_patient_id_foreign');
                }
            });
        }
    }

    /**
     * @return array<int, array{table:string, column:string, foreign_table:string, foreign_column:string, orphan_count:int}>
     */
    private function orphanDefinitions(): array
    {
        return [
            [
                'table' => 'prescriptions',
                'column' => 'patient_id',
                'foreign_table' => 'patients',
                'foreign_column' => 'id',
                'orphan_count' => $this->countOrphans('prescriptions', 'patient_id', 'patients', 'id'),
            ],
            [
                'table' => 'prescriptions',
                'column' => 'treatment_session_id',
                'foreign_table' => 'treatment_sessions',
                'foreign_column' => 'id',
                'orphan_count' => $this->countOrphans('prescriptions', 'treatment_session_id', 'treatment_sessions', 'id'),
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'patient_id',
                'foreign_table' => 'patients',
                'foreign_column' => 'id',
                'orphan_count' => $this->countOrphans('patient_tooth_conditions', 'patient_id', 'patients', 'id'),
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'tooth_condition_id',
                'foreign_table' => 'tooth_conditions',
                'foreign_column' => 'id',
                'orphan_count' => $this->countOrphans('patient_tooth_conditions', 'tooth_condition_id', 'tooth_conditions', 'id'),
            ],
            [
                'table' => 'patient_tooth_conditions',
                'column' => 'treatment_plan_id',
                'foreign_table' => 'treatment_plans',
                'foreign_column' => 'id',
                'orphan_count' => $this->countOrphans('patient_tooth_conditions', 'treatment_plan_id', 'treatment_plans', 'id'),
            ],
        ];
    }

    private function assertNoOrphansBeforeAddingForeignKeys(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasTable('patient_tooth_conditions')) {
            return;
        }

        $orphans = collect($this->orphanDefinitions())
            ->filter(static fn (array $item): bool => $item['orphan_count'] > 0)
            ->map(static fn (array $item): string => sprintf(
                '%s.%s -> %s.%s (%d orphan)',
                $item['table'],
                $item['column'],
                $item['foreign_table'],
                $item['foreign_column'],
                $item['orphan_count'],
            ))
            ->values()
            ->all();

        if ($orphans !== []) {
            throw new \RuntimeException('Khong the them critical foreign keys do ton tai orphan data: '.implode('; ', $orphans));
        }
    }

    private function hasForeignKey(string $table, string $column, string $foreignTable, string $foreignColumn): bool
    {
        $foreignKeys = Schema::getForeignKeys($table);

        return collect($foreignKeys)->contains(function (array $foreignKey) use ($column, $foreignTable, $foreignColumn): bool {
            return (array) ($foreignKey['columns'] ?? []) === [$column]
                && (string) ($foreignKey['foreign_table'] ?? '') === $foreignTable
                && (array) ($foreignKey['foreign_columns'] ?? []) === [$foreignColumn];
        });
    }

    private function hasForeignKeyByName(string $table, string $foreignKeyName): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreignKey): bool => (string) ($foreignKey['name'] ?? '') === $foreignKeyName);
    }

    private function countOrphans(string $table, string $column, string $foreignTable, string $foreignColumn): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($foreignTable) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table.' as source')
            ->leftJoin($foreignTable.' as target', 'target.'.$foreignColumn, '=', 'source.'.$column)
            ->whereNull('target.'.$foreignColumn)
            ->whereNotNull('source.'.$column)
            ->count();
    }
};
