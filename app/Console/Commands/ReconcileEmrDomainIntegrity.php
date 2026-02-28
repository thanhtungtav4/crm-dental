<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\EmrApiMutation;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ReconcileEmrDomainIntegrity extends Command
{
    protected $signature = 'emr:reconcile-integrity
        {--strict : Trả về mã lỗi nếu phát hiện mismatch}
        {--stale-minutes=10 : Ngưỡng mutation pending bị xem là treo}';

    protected $description = 'Đối soát toàn vẹn EMR domain (revision/version/idempotency/order-result).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation đối soát EMR.',
        );

        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $strict = (bool) $this->option('strict');

        $findings = $this->collectFindings($staleMinutes);
        $totalIssues = collect($findings)->sum();

        $this->table(
            ['Check', 'Count'],
            collect($findings)
                ->map(fn (int $count, string $check): array => [$check, $count])
                ->values()
                ->all(),
        );

        $metadata = [
            'command' => 'emr:reconcile-integrity',
            'strict' => $strict,
            'stale_minutes' => $staleMinutes,
            'findings' => $findings,
            'total_issues' => $totalIssues,
        ];

        if ($totalIssues > 0) {
            $this->warn("EMR reconcile phát hiện {$totalIssues} vấn đề.");

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                metadata: $metadata,
            );

            return $strict ? self::FAILURE : self::SUCCESS;
        }

        $this->info('EMR reconcile không phát hiện mismatch.');

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: $metadata,
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    protected function collectFindings(int $staleMinutes): array
    {
        $maxRevisionSubquery = DB::table('clinical_note_revisions')
            ->selectRaw('clinical_note_id, MAX(version) as max_version')
            ->groupBy('clinical_note_id');

        $missingInitialRevision = ClinicalNote::query()
            ->leftJoin('clinical_note_revisions as initial_revision', function (JoinClause $join): void {
                $join->on('initial_revision.clinical_note_id', '=', 'clinical_notes.id')
                    ->where('initial_revision.version', '=', 1);
            })
            ->whereNull('initial_revision.id')
            ->count();

        $versionMismatch = ClinicalNote::query()
            ->leftJoinSub($maxRevisionSubquery, 'revision_max', function (JoinClause $join): void {
                $join->on('revision_max.clinical_note_id', '=', 'clinical_notes.id');
            })
            ->where(function ($query): void {
                $query->whereNull('revision_max.max_version')
                    ->orWhereColumn('clinical_notes.lock_version', '!=', 'revision_max.max_version');
            })
            ->count();

        $stalePendingMutation = EmrApiMutation::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes($staleMinutes))
            ->count();

        $orderResultMismatch = ClinicalResult::query()
            ->join('clinical_orders', 'clinical_orders.id', '=', 'clinical_results.clinical_order_id')
            ->whereIn('clinical_results.status', [ClinicalResult::STATUS_FINAL, ClinicalResult::STATUS_AMENDED])
            ->whereNotIn('clinical_orders.status', [ClinicalOrder::STATUS_COMPLETED, ClinicalOrder::STATUS_CANCELLED])
            ->count();

        return [
            'missing_initial_revision' => (int) $missingInitialRevision,
            'note_revision_version_mismatch' => (int) $versionMismatch,
            'stale_pending_mutation' => (int) $stalePendingMutation,
            'order_result_state_mismatch' => (int) $orderResultMismatch,
        ];
    }
}
