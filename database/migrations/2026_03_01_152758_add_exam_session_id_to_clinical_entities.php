<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('clinical_notes') && ! Schema::hasColumn('clinical_notes', 'exam_session_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->foreignId('exam_session_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('exam_sessions')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('clinical_orders') && ! Schema::hasColumn('clinical_orders', 'exam_session_id')) {
            Schema::table('clinical_orders', function (Blueprint $table): void {
                $table->foreignId('exam_session_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('exam_sessions')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'exam_session_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->foreignId('exam_session_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('exam_sessions')
                    ->nullOnDelete();
            });
        }

        $this->backfillExamSessionForClinicalNotes();
        $this->backfillExamSessionForClinicalOrders();
        $this->backfillExamSessionForPrescriptions();

        if (Schema::hasTable('clinical_notes') && Schema::hasColumn('clinical_notes', 'exam_session_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->index(['exam_session_id', 'date'], 'clinical_notes_exam_session_date_idx');
            });
        }

        if (Schema::hasTable('clinical_orders') && Schema::hasColumn('clinical_orders', 'exam_session_id')) {
            Schema::table('clinical_orders', function (Blueprint $table): void {
                $table->index(['exam_session_id', 'status'], 'clinical_orders_exam_session_status_idx');
            });
        }

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'exam_session_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->index(['exam_session_id', 'treatment_date'], 'prescriptions_exam_session_treatment_date_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'exam_session_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->dropIndex('prescriptions_exam_session_treatment_date_idx');
                $table->dropConstrainedForeignId('exam_session_id');
            });
        }

        if (Schema::hasTable('clinical_orders') && Schema::hasColumn('clinical_orders', 'exam_session_id')) {
            Schema::table('clinical_orders', function (Blueprint $table): void {
                $table->dropIndex('clinical_orders_exam_session_status_idx');
                $table->dropConstrainedForeignId('exam_session_id');
            });
        }

        if (Schema::hasTable('clinical_notes') && Schema::hasColumn('clinical_notes', 'exam_session_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->dropIndex('clinical_notes_exam_session_date_idx');
                $table->dropConstrainedForeignId('exam_session_id');
            });
        }
    }

    protected function backfillExamSessionForClinicalNotes(): void
    {
        if (! Schema::hasTable('clinical_notes') || ! Schema::hasColumn('clinical_notes', 'exam_session_id')) {
            return;
        }

        DB::table('clinical_notes')
            ->select([
                'id',
                'patient_id',
                'visit_episode_id',
                'branch_id',
                'doctor_id',
                'examining_doctor_id',
                'treating_doctor_id',
                'date',
                'general_exam_notes',
                'treatment_plan_note',
                'indications',
                'tooth_diagnosis_data',
                'other_diagnosis',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->whereNull('exam_session_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $doctorId = $row->examining_doctor_id
                        ?? $row->treating_doctor_id
                        ?? $row->doctor_id;

                    $sessionId = DB::table('exam_sessions')->insertGetId([
                        'patient_id' => (int) $row->patient_id,
                        'visit_episode_id' => $row->visit_episode_id ? (int) $row->visit_episode_id : null,
                        'branch_id' => $row->branch_id ? (int) $row->branch_id : null,
                        'doctor_id' => $doctorId ? (int) $doctorId : null,
                        'session_date' => $row->date ?: now()->toDateString(),
                        'status' => $this->resolveStatusFromClinicalNote($row),
                        'created_by' => $row->created_by ? (int) $row->created_by : null,
                        'updated_by' => $row->updated_by ? (int) $row->updated_by : null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    DB::table('clinical_notes')
                        ->where('id', (int) $row->id)
                        ->update(['exam_session_id' => $sessionId]);
                }
            });
    }

    protected function backfillExamSessionForClinicalOrders(): void
    {
        if (! Schema::hasTable('clinical_orders') || ! Schema::hasColumn('clinical_orders', 'exam_session_id')) {
            return;
        }

        DB::table('clinical_orders')
            ->select(['id', 'patient_id', 'visit_episode_id', 'clinical_note_id', 'requested_at'])
            ->whereNull('exam_session_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $sessionId = null;

                    if ($row->clinical_note_id) {
                        $sessionId = DB::table('clinical_notes')
                            ->where('id', (int) $row->clinical_note_id)
                            ->value('exam_session_id');
                    }

                    if (! $sessionId && $row->patient_id) {
                        $sessionId = $this->resolveExamSessionByPatientAndDate(
                            patientId: (int) $row->patient_id,
                            visitEpisodeId: $row->visit_episode_id ? (int) $row->visit_episode_id : null,
                            targetDate: $row->requested_at ? substr((string) $row->requested_at, 0, 10) : null,
                        );
                    }

                    if (! $sessionId) {
                        continue;
                    }

                    DB::table('clinical_orders')
                        ->where('id', (int) $row->id)
                        ->update(['exam_session_id' => (int) $sessionId]);
                }
            });
    }

    protected function backfillExamSessionForPrescriptions(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasColumn('prescriptions', 'exam_session_id')) {
            return;
        }

        DB::table('prescriptions')
            ->select(['id', 'patient_id', 'visit_episode_id', 'treatment_date'])
            ->whereNull('exam_session_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    if (! $row->patient_id) {
                        continue;
                    }

                    $sessionId = $this->resolveExamSessionByPatientAndDate(
                        patientId: (int) $row->patient_id,
                        visitEpisodeId: $row->visit_episode_id ? (int) $row->visit_episode_id : null,
                        targetDate: $row->treatment_date ?: null,
                    );

                    if (! $sessionId) {
                        continue;
                    }

                    DB::table('prescriptions')
                        ->where('id', (int) $row->id)
                        ->update(['exam_session_id' => (int) $sessionId]);
                }
            });
    }

    protected function resolveExamSessionByPatientAndDate(int $patientId, ?int $visitEpisodeId, ?string $targetDate): ?int
    {
        $query = DB::table('exam_sessions')
            ->where('patient_id', $patientId);

        if ($visitEpisodeId !== null) {
            $query->where('visit_episode_id', $visitEpisodeId);
        }

        if ($targetDate !== null) {
            $query->whereDate('session_date', $targetDate);
        }

        $sessionId = $query
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->value('id');

        return $sessionId !== null ? (int) $sessionId : null;
    }

    protected function resolveStatusFromClinicalNote(object $row): string
    {
        $hasClinicalPayload = filled($row->general_exam_notes)
            || filled($row->treatment_plan_note)
            || $this->hasNonEmptyJsonArray($row->indications)
            || $this->hasNonEmptyJsonArray($row->tooth_diagnosis_data)
            || filled($row->other_diagnosis);

        if ($hasClinicalPayload) {
            return 'in_progress';
        }

        if ($row->visit_episode_id || $row->examining_doctor_id || $row->treating_doctor_id || $row->doctor_id) {
            return 'planned';
        }

        return 'draft';
    }

    protected function hasNonEmptyJsonArray(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return ! empty(array_filter($value, fn ($item) => filled($item)));
        }

        if (! is_string($value)) {
            return false;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return false;
        }

        return ! empty(array_filter($decoded, fn ($item) => filled($item)));
    }
};
