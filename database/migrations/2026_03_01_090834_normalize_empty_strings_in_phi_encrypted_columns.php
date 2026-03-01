<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->columnsToNormalize() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, '')
                    ->update([$column => null]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank: normalized NULL values should stay NULL.
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function columnsToNormalize(): array
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
};
