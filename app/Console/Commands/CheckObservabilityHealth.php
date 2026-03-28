<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\IntegrationOperationalReadModelService;
use App\Services\OperationalAutomationAuditReadModelService;
use App\Services\OperationalKpiAlertReadModelService;
use App\Services\OperationalKpiSnapshotReadModelService;
use App\Services\OpsCommandAuthorizer;
use App\Services\ZnsOperationalReadModelService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckObservabilityHealth extends Command
{
    protected $signature = 'ops:check-observability-health
        {--window-hours= : Cửa sổ lookback (giờ) cho chỉ số lỗi automation}
        {--snapshot-date= : Ngày snapshot để kiểm tra SLA (Y-m-d), mặc định hôm qua}
        {--strict : Fail command khi vượt error budget}
        {--dry-run : Chỉ preview, không ghi audit log}';

    protected $description = 'Kiểm tra observability cross-module (SLO/error budget/runbook) cho release gate production.';

    public function __construct(
        protected OpsCommandAuthorizer $authorizer,
        protected IntegrationOperationalReadModelService $integrationOperationalReadModelService,
        protected OperationalAutomationAuditReadModelService $operationalAutomationAuditReadModelService,
        protected OperationalKpiAlertReadModelService $operationalKpiAlertReadModelService,
        protected OperationalKpiSnapshotReadModelService $operationalKpiSnapshotReadModelService,
        protected ZnsOperationalReadModelService $znsOperationalReadModelService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $actorId = $this->authorizer->authorize(
            'Bạn không có quyền kiểm tra observability cross-module.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');
        $windowHours = $this->resolveWindowHours();
        $snapshotDate = $this->resolveSnapshotDate();
        $windowStartedAt = now()->subHours($windowHours);
        $runbookMap = ClinicRuntimeSettings::opsAlertRunbookMap();

        $deadBacklog = [
            'google' => $this->integrationOperationalReadModelService->googleCalendarDeadBacklogCount(),
            'emr' => $this->integrationOperationalReadModelService->emrDeadBacklogCount(),
            'zns' => $this->znsOperationalReadModelService->automationDeadCount(),
        ];
        $failedBacklog = [
            'google' => $this->integrationOperationalReadModelService->googleCalendarFailedBacklogCount(),
            'emr' => $this->integrationOperationalReadModelService->emrFailedBacklogCount(),
            'zns' => $this->znsOperationalReadModelService->automationFailedCount(),
        ];

        $metrics = [
            'dead_backlog_total' => array_sum($deadBacklog),
            'retryable_failed_backlog_total' => array_sum($failedBacklog),
            'open_kpi_alerts' => $this->operationalKpiAlertReadModelService->openAlertCount(),
            'snapshot_sla_violations' => $this->operationalKpiSnapshotReadModelService
                ->slaViolationCountForDate($snapshotDate),
            'recent_automation_failures' => $this->operationalAutomationAuditReadModelService
                ->recentFailureCount($windowStartedAt),
        ];

        $requiredRunbookCategories = [
            'google_calendar_dead_letter',
            'emr_dead_letter',
            'zns_automation_dead_letter',
            'cross_module_observability',
        ];

        $missingRunbookCategories = collect($requiredRunbookCategories)
            ->filter(function (string $category) use ($runbookMap): bool {
                $ownerRole = trim((string) data_get($runbookMap, "{$category}.owner_role", ''));
                $threshold = trim((string) data_get($runbookMap, "{$category}.threshold", ''));
                $runbook = trim((string) data_get($runbookMap, "{$category}.runbook", ''));

                return $ownerRole === '' || $threshold === '' || $runbook === '';
            })
            ->values()
            ->all();

        $metrics['missing_runbook_categories'] = count($missingRunbookCategories);

        $budgets = [
            'dead_backlog_total' => ClinicRuntimeSettings::observabilityDeadLetterErrorBudget(),
            'retryable_failed_backlog_total' => ClinicRuntimeSettings::observabilityRetryableFailureErrorBudget(),
            'open_kpi_alerts' => ClinicRuntimeSettings::observabilityOpenKpiAlertErrorBudget(),
            'snapshot_sla_violations' => ClinicRuntimeSettings::observabilitySnapshotSlaErrorBudget(),
            'recent_automation_failures' => ClinicRuntimeSettings::observabilityRecentAutomationFailureErrorBudget(),
            'missing_runbook_categories' => 0,
        ];

        $breaches = collect($budgets)
            ->map(function (int $budget, string $metricKey) use ($metrics): ?array {
                $value = (int) ($metrics[$metricKey] ?? 0);

                if ($value <= $budget) {
                    return null;
                }

                return [
                    'metric' => $metricKey,
                    'value' => $value,
                    'budget' => $budget,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $healthy = $breaches === [];

        $this->line('OBS_WINDOW_HOURS: '.$windowHours);
        $this->line('OBS_WINDOW_STARTED_AT: '.$windowStartedAt->toDateTimeString());
        $this->line('OBS_SNAPSHOT_DATE: '.$snapshotDate);
        $this->line('OBS_DEAD_BACKLOG_GOOGLE: '.(string) $deadBacklog['google']);
        $this->line('OBS_DEAD_BACKLOG_EMR: '.(string) $deadBacklog['emr']);
        $this->line('OBS_DEAD_BACKLOG_ZNS: '.(string) $deadBacklog['zns']);
        $this->line('OBS_RETRYABLE_FAILED_BACKLOG_GOOGLE: '.(string) $failedBacklog['google']);
        $this->line('OBS_RETRYABLE_FAILED_BACKLOG_EMR: '.(string) $failedBacklog['emr']);
        $this->line('OBS_RETRYABLE_FAILED_BACKLOG_ZNS: '.(string) $failedBacklog['zns']);
        $this->line('OBS_OPEN_KPI_ALERTS: '.(string) $metrics['open_kpi_alerts']);
        $this->line('OBS_SNAPSHOT_SLA_VIOLATIONS: '.(string) $metrics['snapshot_sla_violations']);
        $this->line('OBS_RECENT_AUTOMATION_FAILURES: '.(string) $metrics['recent_automation_failures']);
        $this->line('OBS_MISSING_RUNBOOK_CATEGORIES: '.(string) $metrics['missing_runbook_categories']);
        $this->line('OBS_BREACH_COUNT: '.count($breaches));

        if ($missingRunbookCategories !== []) {
            $this->warn('OBS_MISSING_RUNBOOK_LIST: '.implode(', ', $missingRunbookCategories));
        }

        if ($breaches !== []) {
            $this->table(
                ['Metric', 'Value', 'Budget'],
                collect($breaches)->map(fn (array $breach): array => [
                    (string) $breach['metric'],
                    (string) $breach['value'],
                    (string) $breach['budget'],
                ])->all(),
            );
        }

        $status = $healthy ? 'healthy' : 'unhealthy';
        $this->line('OBS_HEALTH_STATUS: '.$status);

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: $healthy ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
                actorId: $actorId,
                metadata: [
                    'channel' => 'observability_health',
                    'command' => 'ops:check-observability-health',
                    'strict' => $strict,
                    'status' => $status,
                    'window_hours' => $windowHours,
                    'window_started_at' => $windowStartedAt->toDateTimeString(),
                    'snapshot_date' => $snapshotDate,
                    'metrics' => $metrics,
                    'budgets' => $budgets,
                    'breaches' => $breaches,
                    'tracked_failure_commands' => $this->operationalAutomationAuditReadModelService->trackedCommands(),
                    'tracked_failure_channels' => $this->operationalAutomationAuditReadModelService->trackedChannels(),
                    'missing_runbook_categories' => $missingRunbookCategories,
                    'runbook_category' => 'cross_module_observability',
                    'runbook' => (string) data_get($runbookMap, 'cross_module_observability.runbook', ''),
                ],
            );
        }

        if ($strict && ! $healthy) {
            $this->error('Strict mode: observability health vượt error budget.');

            return self::FAILURE;
        }

        $this->info('Observability health check completed.');

        return self::SUCCESS;
    }

    protected function resolveWindowHours(): int
    {
        $rawWindowHours = $this->option('window-hours');
        $windowHours = is_numeric($rawWindowHours)
            ? (int) $rawWindowHours
            : ClinicRuntimeSettings::observabilityWindowHours();

        return max(1, $windowHours);
    }

    protected function resolveSnapshotDate(): string
    {
        $rawDate = trim((string) ($this->option('snapshot-date') ?? ''));

        if ($rawDate === '') {
            return now()->subDay()->toDateString();
        }

        return Carbon::parse($rawDate)->toDateString();
    }
}
