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
     * Run the migrations.
     */
    public function up(): void
    {
        $this->encryptTextColumns();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->decryptTextColumns();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function columnsToEncrypt(): array
    {
        return [
            'patients' => [
                'medical_history',
            ],
            'patient_medical_records' => [
                'additional_notes',
            ],
            'clinical_notes' => [
                'examination_note',
                'general_exam_notes',
                'recommendation_notes',
                'treatment_plan_note',
                'other_diagnosis',
            ],
            'prescriptions' => [
                'notes',
            ],
            'prescription_items' => [
                'notes',
            ],
            'consents' => [
                'note',
            ],
            'clinical_orders' => [
                'notes',
            ],
            'clinical_results' => [
                'interpretation',
                'notes',
            ],
        ];
    }

    protected function encryptTextColumns(): void
    {
        foreach ($this->columnsToEncrypt() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->transformColumnValues(
                    table: $table,
                    column: $column,
                    transform: fn (string $value): string => Crypt::encryptString($value),
                    shouldTransform: fn (string $value): bool => ! $this->isEncrypted($value),
                );
            }
        }
    }

    protected function decryptTextColumns(): void
    {
        foreach ($this->columnsToEncrypt() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->transformColumnValues(
                    table: $table,
                    column: $column,
                    transform: fn (string $value): string => Crypt::decryptString($value),
                    shouldTransform: fn (string $value): bool => $this->isEncrypted($value),
                );
            }
        }
    }

    /**
     * @param  callable(string): string  $transform
     * @param  callable(string): bool  $shouldTransform
     */
    protected function transformColumnValues(
        string $table,
        string $column,
        callable $transform,
        callable $shouldTransform,
    ): void {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows) use ($table, $column, $transform, $shouldTransform): void {
                foreach ($rows as $row) {
                    $rawValue = (string) ($row->{$column} ?? '');

                    if ($rawValue === '' || ! $shouldTransform($rawValue)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', (int) $row->id)
                        ->update([$column => $transform($rawValue)]);
                }
            });
    }

    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
