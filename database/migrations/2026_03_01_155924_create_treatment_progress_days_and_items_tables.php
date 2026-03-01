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
        if (! Schema::hasTable('treatment_progress_days')) {
            Schema::create('treatment_progress_days', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->date('progress_date');
                $table->string('status', 30)->default('planned');
                $table->text('notes')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('locked_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['patient_id', 'exam_session_id', 'progress_date'],
                    'treatment_progress_days_patient_exam_date_unique'
                );
                $table->index(['patient_id', 'progress_date'], 'treatment_progress_days_patient_date_idx');
                $table->index(['exam_session_id', 'status'], 'treatment_progress_days_exam_status_idx');
                $table->index(['branch_id', 'progress_date'], 'treatment_progress_days_branch_date_idx');
            });
        }

        if (! Schema::hasTable('treatment_progress_items')) {
            Schema::create('treatment_progress_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('treatment_progress_day_id')->constrained('treatment_progress_days')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
                $table->foreignId('plan_item_id')->nullable()->constrained('plan_items')->nullOnDelete();
                $table->foreignId('treatment_session_id')->nullable()->constrained('treatment_sessions')->nullOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assistant_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tooth_number', 120)->nullable();
                $table->string('procedure_name', 255)->nullable();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status', 30)->default('planned');
                $table->dateTime('performed_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('treatment_session_id', 'treatment_progress_items_treatment_session_unique');
                $table->index(['treatment_progress_day_id', 'status'], 'treatment_progress_items_day_status_idx');
                $table->index(['patient_id', 'performed_at'], 'treatment_progress_items_patient_performed_idx');
                $table->index(['exam_session_id', 'status'], 'treatment_progress_items_exam_status_idx');
            });
        }

        if (Schema::hasTable('treatment_sessions') && ! Schema::hasColumn('treatment_sessions', 'exam_session_id')) {
            Schema::table('treatment_sessions', function (Blueprint $table): void {
                $table->foreignId('exam_session_id')
                    ->nullable()
                    ->after('treatment_plan_id')
                    ->constrained('exam_sessions')
                    ->nullOnDelete();
                $table->index(['exam_session_id', 'performed_at'], 'treatment_sessions_exam_performed_idx');
            });
        }

        $this->backfillProgressDataFromTreatmentSessions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('treatment_sessions') && Schema::hasColumn('treatment_sessions', 'exam_session_id')) {
            Schema::table('treatment_sessions', function (Blueprint $table): void {
                $table->dropIndex('treatment_sessions_exam_performed_idx');
                $table->dropConstrainedForeignId('exam_session_id');
            });
        }

        Schema::dropIfExists('treatment_progress_items');
        Schema::dropIfExists('treatment_progress_days');
    }

    protected function backfillProgressDataFromTreatmentSessions(): void
    {
        if (! Schema::hasTable('treatment_sessions')) {
            return;
        }

        DB::table('treatment_sessions')
            ->leftJoin('plan_items', 'plan_items.id', '=', 'treatment_sessions.plan_item_id')
            ->leftJoin('treatment_plans', 'treatment_plans.id', '=', 'treatment_sessions.treatment_plan_id')
            ->select([
                'treatment_sessions.id',
                'treatment_sessions.treatment_plan_id',
                'treatment_sessions.plan_item_id',
                'treatment_sessions.doctor_id',
                'treatment_sessions.assistant_id',
                'treatment_sessions.performed_at',
                'treatment_sessions.start_at',
                'treatment_sessions.end_at',
                'treatment_sessions.notes',
                'treatment_sessions.status',
                'treatment_sessions.created_by',
                'treatment_sessions.updated_by',
                'treatment_sessions.created_at',
                'treatment_sessions.updated_at',
                'plan_items.name as plan_item_name',
                'plan_items.tooth_number as plan_item_tooth_number',
                'plan_items.quantity as plan_item_quantity',
                'plan_items.price as plan_item_price',
                'plan_items.final_amount as plan_item_final_amount',
                'treatment_plans.patient_id as patient_id',
                'treatment_plans.branch_id as branch_id',
            ])
            ->orderBy('treatment_sessions.id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    if (! $row->patient_id) {
                        continue;
                    }

                    $progressDate = $this->resolveProgressDate($row);
                    $examSessionId = $this->resolveOrCreateExamSessionId(
                        patientId: (int) $row->patient_id,
                        branchId: $row->branch_id ? (int) $row->branch_id : null,
                        treatmentPlanId: $row->treatment_plan_id ? (int) $row->treatment_plan_id : null,
                        doctorId: $row->doctor_id ? (int) $row->doctor_id : null,
                        progressDate: $progressDate,
                        status: (string) ($row->status ?: 'scheduled'),
                        createdBy: $row->created_by ? (int) $row->created_by : null,
                        updatedBy: $row->updated_by ? (int) $row->updated_by : null,
                        createdAt: $row->created_at,
                        updatedAt: $row->updated_at,
                    );

                    DB::table('treatment_sessions')
                        ->where('id', (int) $row->id)
                        ->update([
                            'exam_session_id' => $examSessionId,
                        ]);

                    $dayId = $this->resolveOrCreateProgressDay(
                        patientId: (int) $row->patient_id,
                        examSessionId: $examSessionId,
                        treatmentPlanId: $row->treatment_plan_id ? (int) $row->treatment_plan_id : null,
                        branchId: $row->branch_id ? (int) $row->branch_id : null,
                        progressDate: $progressDate,
                        status: (string) ($row->status ?: 'scheduled'),
                        createdBy: $row->created_by ? (int) $row->created_by : null,
                        updatedBy: $row->updated_by ? (int) $row->updated_by : null,
                        createdAt: $row->created_at,
                        updatedAt: $row->updated_at,
                    );

                    $quantity = max(1, (float) ($row->plan_item_quantity ?? 1));
                    $unitPrice = (float) ($row->plan_item_price ?? 0);
                    $lineTotal = (float) ($row->plan_item_final_amount ?? ($quantity * $unitPrice));

                    DB::table('treatment_progress_items')->updateOrInsert(
                        ['treatment_session_id' => (int) $row->id],
                        [
                            'treatment_progress_day_id' => $dayId,
                            'patient_id' => (int) $row->patient_id,
                            'exam_session_id' => $examSessionId,
                            'treatment_plan_id' => $row->treatment_plan_id ? (int) $row->treatment_plan_id : null,
                            'plan_item_id' => $row->plan_item_id ? (int) $row->plan_item_id : null,
                            'doctor_id' => $row->doctor_id ? (int) $row->doctor_id : null,
                            'assistant_id' => $row->assistant_id ? (int) $row->assistant_id : null,
                            'tooth_number' => $row->plan_item_tooth_number,
                            'procedure_name' => $row->plan_item_name,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_amount' => $lineTotal,
                            'status' => $this->normalizeProgressItemStatus((string) ($row->status ?: 'scheduled')),
                            'performed_at' => $row->performed_at ?? $row->start_at ?? $row->created_at,
                            'notes' => $row->notes,
                            'created_by' => $row->created_by ? (int) $row->created_by : null,
                            'updated_by' => $row->updated_by ? (int) $row->updated_by : null,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]
                    );

                    $this->refreshProgressDayStatus($dayId);
                }
            }, 'treatment_sessions.id', 'id');
    }

    protected function resolveProgressDate(object $sessionRow): string
    {
        $dateTime = $sessionRow->performed_at
            ?? $sessionRow->start_at
            ?? $sessionRow->end_at
            ?? $sessionRow->created_at
            ?? now();

        return substr((string) $dateTime, 0, 10);
    }

    protected function resolveOrCreateExamSessionId(
        int $patientId,
        ?int $branchId,
        ?int $treatmentPlanId,
        ?int $doctorId,
        string $progressDate,
        string $status,
        ?int $createdBy,
        ?int $updatedBy,
        mixed $createdAt,
        mixed $updatedAt
    ): ?int {
        $query = DB::table('exam_sessions')
            ->where('patient_id', $patientId)
            ->whereDate('session_date', $progressDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $existingId = $query
            ->orderByDesc('id')
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        $sessionStatus = in_array($status, ['done', 'completed'], true)
            ? 'completed'
            : 'in_progress';

        return (int) DB::table('exam_sessions')->insertGetId([
            'patient_id' => $patientId,
            'visit_episode_id' => $this->resolveVisitEpisodeId($patientId, $branchId, $progressDate),
            'branch_id' => $branchId,
            'doctor_id' => $doctorId,
            'session_date' => $progressDate,
            'status' => $sessionStatus,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $updatedAt ?? now(),
        ]);
    }

    protected function resolveOrCreateProgressDay(
        int $patientId,
        ?int $examSessionId,
        ?int $treatmentPlanId,
        ?int $branchId,
        string $progressDate,
        string $status,
        ?int $createdBy,
        ?int $updatedBy,
        mixed $createdAt,
        mixed $updatedAt
    ): int {
        $query = DB::table('treatment_progress_days')
            ->where('patient_id', $patientId)
            ->whereDate('progress_date', $progressDate);

        if ($examSessionId !== null) {
            $query->where('exam_session_id', $examSessionId);
        } else {
            $query->whereNull('exam_session_id');
        }

        $existing = $query
            ->orderByDesc('id')
            ->first(['id', 'status', 'started_at', 'completed_at']);

        $normalizedStatus = in_array($status, ['done', 'completed'], true) ? 'completed' : 'in_progress';
        $startedAt = $normalizedStatus === 'completed' ? ($createdAt ?? now()) : null;
        $completedAt = $normalizedStatus === 'completed' ? ($updatedAt ?? now()) : null;

        if ($existing) {
            $updates = [];
            if ($existing->status !== 'locked') {
                $updates['status'] = $normalizedStatus;
            }
            if (! $existing->started_at) {
                $updates['started_at'] = $startedAt;
            }
            if ($normalizedStatus === 'completed' && ! $existing->completed_at) {
                $updates['completed_at'] = $completedAt;
            }
            if ($updates !== []) {
                $updates['updated_at'] = $updatedAt ?? now();
                DB::table('treatment_progress_days')
                    ->where('id', (int) $existing->id)
                    ->update($updates);
            }

            return (int) $existing->id;
        }

        return (int) DB::table('treatment_progress_days')->insertGetId([
            'patient_id' => $patientId,
            'exam_session_id' => $examSessionId,
            'treatment_plan_id' => $treatmentPlanId,
            'branch_id' => $branchId,
            'progress_date' => $progressDate,
            'status' => $normalizedStatus,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'locked_at' => null,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $updatedAt ?? now(),
        ]);
    }

    protected function normalizeProgressItemStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'done', 'completed' => 'completed',
            'follow_up', 'follow-up', 'in_progress', 'in progress' => 'in_progress',
            'cancelled', 'canceled' => 'cancelled',
            default => 'planned',
        };
    }

    protected function refreshProgressDayStatus(int $progressDayId): void
    {
        $day = DB::table('treatment_progress_days')
            ->where('id', $progressDayId)
            ->first(['id', 'status', 'started_at', 'completed_at']);

        if (! $day || $day->status === 'locked') {
            return;
        }

        $statusCounts = DB::table('treatment_progress_items')
            ->where('treatment_progress_day_id', $progressDayId)
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalItems = (int) $statusCounts->sum();
        if ($totalItems === 0) {
            return;
        }

        $inProgressCount = (int) ($statusCounts['in_progress'] ?? 0);
        $plannedCount = (int) ($statusCounts['planned'] ?? 0);
        $completedCount = (int) ($statusCounts['completed'] ?? 0);

        $status = 'planned';

        if ($completedCount === $totalItems) {
            $status = 'completed';
        } elseif (($inProgressCount + $completedCount) > 0) {
            $status = 'in_progress';
        } elseif ($plannedCount > 0) {
            $status = 'planned';
        }

        $startedAtValue = $day->started_at;
        if ($startedAtValue === null && $status !== 'planned') {
            $startedAtValue = now();
        }

        $completedAtValue = $day->completed_at;
        if ($status === 'completed' && $completedAtValue === null) {
            $completedAtValue = now();
        }
        if ($status !== 'completed') {
            $completedAtValue = null;
        }

        DB::table('treatment_progress_days')
            ->where('id', $progressDayId)
            ->update([
                'status' => $status,
                'started_at' => $startedAtValue,
                'completed_at' => $completedAtValue,
                'updated_at' => now(),
            ]);
    }

    protected function resolveVisitEpisodeId(int $patientId, ?int $branchId, string $progressDate): ?int
    {
        $query = DB::table('visit_episodes')
            ->where('patient_id', $patientId)
            ->whereDate('scheduled_at', $progressDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $episodeId = $query
            ->orderByRaw('appointment_id IS NULL')
            ->orderByDesc('scheduled_at')
            ->value('id');

        return $episodeId !== null ? (int) $episodeId : null;
    }
};
