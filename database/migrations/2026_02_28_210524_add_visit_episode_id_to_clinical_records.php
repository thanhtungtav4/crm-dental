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
        if (Schema::hasTable('clinical_notes') && ! Schema::hasColumn('clinical_notes', 'visit_episode_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->foreignId('visit_episode_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('visit_episodes')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'visit_episode_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->foreignId('visit_episode_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('visit_episodes')
                    ->nullOnDelete();
            });
        }

        $this->backfillClinicalNotesVisitEpisode();
        $this->backfillPrescriptionsVisitEpisode();

        if (Schema::hasTable('clinical_notes') && Schema::hasColumn('clinical_notes', 'visit_episode_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->index(['visit_episode_id', 'date'], 'clinical_notes_visit_episode_date_index');
            });
        }

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'visit_episode_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->index(['visit_episode_id', 'treatment_date'], 'prescriptions_visit_episode_treatment_date_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'visit_episode_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->dropIndex('prescriptions_visit_episode_treatment_date_index');
                $table->dropConstrainedForeignId('visit_episode_id');
            });
        }

        if (Schema::hasTable('clinical_notes') && Schema::hasColumn('clinical_notes', 'visit_episode_id')) {
            Schema::table('clinical_notes', function (Blueprint $table): void {
                $table->dropIndex('clinical_notes_visit_episode_date_index');
                $table->dropConstrainedForeignId('visit_episode_id');
            });
        }
    }

    protected function backfillClinicalNotesVisitEpisode(): void
    {
        if (! Schema::hasTable('clinical_notes') || ! Schema::hasColumn('clinical_notes', 'visit_episode_id')) {
            return;
        }

        DB::table('clinical_notes')
            ->select(['id', 'patient_id', 'branch_id', 'date'])
            ->whereNull('visit_episode_id')
            ->whereNotNull('patient_id')
            ->whereNotNull('date')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $episodeId = $this->resolveVisitEpisodeId(
                        patientId: (int) $row->patient_id,
                        branchId: $row->branch_id !== null ? (int) $row->branch_id : null,
                        date: (string) $row->date,
                    );

                    if ($episodeId === null) {
                        continue;
                    }

                    DB::table('clinical_notes')
                        ->where('id', (int) $row->id)
                        ->update(['visit_episode_id' => $episodeId]);
                }
            });
    }

    protected function backfillPrescriptionsVisitEpisode(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasColumn('prescriptions', 'visit_episode_id')) {
            return;
        }

        DB::table('prescriptions')
            ->select(['id', 'patient_id', 'branch_id', 'treatment_date'])
            ->whereNull('visit_episode_id')
            ->whereNotNull('patient_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $targetDate = $row->treatment_date ?: now()->toDateString();
                    $episodeId = $this->resolveVisitEpisodeId(
                        patientId: (int) $row->patient_id,
                        branchId: $row->branch_id !== null ? (int) $row->branch_id : null,
                        date: (string) $targetDate,
                    );

                    if ($episodeId === null) {
                        continue;
                    }

                    DB::table('prescriptions')
                        ->where('id', (int) $row->id)
                        ->update(['visit_episode_id' => $episodeId]);
                }
            });
    }

    protected function resolveVisitEpisodeId(int $patientId, ?int $branchId, string $date): ?int
    {
        $query = DB::table('visit_episodes')
            ->where('patient_id', $patientId)
            ->whereDate('scheduled_at', $date);

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
