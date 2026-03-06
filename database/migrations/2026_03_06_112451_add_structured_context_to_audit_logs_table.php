<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('actor_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('patient_id')
                ->nullable()
                ->after('branch_id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('occurred_at')
                ->nullable()
                ->after('metadata');

            $table->index(['branch_id', 'occurred_at'], 'audit_logs_branch_occurred_idx');
            $table->index(['patient_id', 'occurred_at'], 'audit_logs_patient_occurred_idx');
            $table->index(['action', 'occurred_at'], 'audit_logs_action_occurred_idx');
        });

        DB::table('audit_logs')
            ->select(['id', 'metadata', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                $logs = $rows
                    ->map(function (object $row): array {
                        $metadata = json_decode((string) ($row->metadata ?? '[]'), true);

                        return [
                            'id' => (int) $row->id,
                            'created_at' => $row->created_at,
                            'metadata' => is_array($metadata) ? $metadata : [],
                            'patient_id' => $this->normalizeNullableInt(data_get($metadata, 'patient_id')),
                        ];
                    })
                    ->values();

                $patientBranchMap = DB::table('patients')
                    ->whereIn('id', $logs->pluck('patient_id')->filter()->unique()->all())
                    ->pluck('first_branch_id', 'id')
                    ->mapWithKeys(fn (mixed $branchId, mixed $patientId): array => [(int) $patientId => $this->normalizeNullableInt($branchId)])
                    ->all();

                foreach ($logs as $log) {
                    $branchId = $this->normalizeNullableInt(data_get($log['metadata'], 'branch_id'))
                        ?? $this->normalizeNullableInt(data_get($log['metadata'], 'source_branch_id'))
                        ?? $this->normalizeNullableInt(data_get($log['metadata'], 'to_branch_id'))
                        ?? $this->normalizeNullableInt(data_get($log['metadata'], 'from_branch_id'))
                        ?? ($log['patient_id'] !== null ? ($patientBranchMap[$log['patient_id']] ?? null) : null);

                    DB::table('audit_logs')
                        ->where('id', $log['id'])
                        ->update([
                            'branch_id' => $branchId,
                            'patient_id' => $log['patient_id'],
                            'occurred_at' => $log['created_at'] ?? now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_branch_occurred_idx');
            $table->dropIndex('audit_logs_patient_occurred_idx');
            $table->dropIndex('audit_logs_action_occurred_idx');
            $table->dropConstrainedForeignId('patient_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('occurred_at');
        });
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
};
