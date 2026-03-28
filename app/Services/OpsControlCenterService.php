<?php

namespace App\Services;

use App\Filament\Pages\FinancialDashboard;
use App\Filament\Pages\IntegrationSettings;
use App\Filament\Pages\Reports\CustomsCareStatistical;
use App\Filament\Pages\Reports\OperationalKpiPack;
use App\Filament\Pages\Reports\RevenueStatistical;
use App\Filament\Pages\Reports\RiskScoringDashboard;
use App\Filament\Pages\ZaloZns;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WebLeadEmailDeliveries\WebLeadEmailDeliveryResource;
use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\AuditLog;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Carbon\CarbonInterface;
use Database\Seeders\FinanceScenarioSeeder;
use Database\Seeders\GovernanceScenarioSeeder;
use Database\Seeders\KpiScenarioSeeder;
use Database\Seeders\OpsScenarioSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class OpsControlCenterService
{
    public function __construct(
        protected AutomationActorResolver $automationActorResolver,
        protected BackupArtifactManifestService $manifestService,
        protected GovernanceAuditReadModelService $governanceAuditReadModelService,
        protected IntegrationOperationalReadModelService $integrationOperationalReadModelService,
        protected IntegrationProviderHealthReadModelService $integrationProviderHealthReadModelService,
        protected HotReportAggregateReadinessService $hotReportAggregateReadinessService,
        protected OperationalAutomationAuditReadModelService $operationalAutomationAuditReadModelService,
        protected OperationalKpiAlertReadModelService $operationalKpiAlertReadModelService,
        protected OperationalKpiSnapshotReadModelService $operationalKpiSnapshotReadModelService,
        protected ZnsOperationalReadModelService $znsOperationalReadModelService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $automationActor = $this->automationActorSummary();
        $runtimeBackup = $this->inspectBackupDirectory(
            label: 'Runtime backup path',
            path: storage_path('app/backups'),
            description: 'Đường dẫn runtime thật để release gate và restore drill kiểm tra.',
        );
        $backupFixtures = [
            $this->inspectBackupDirectory(
                label: 'Backup pass fixture',
                path: OpsScenarioSeeder::readyBackupPath(),
                description: 'Fixture backup hợp lệ để chạy smoke local.',
            ),
            $this->inspectBackupDirectory(
                label: 'Backup fail fixture (missing manifest)',
                path: OpsScenarioSeeder::failMissingManifestBackupPath(),
                description: 'Fixture fail-path khi manifest bị thiếu.',
            ),
            $this->inspectBackupDirectory(
                label: 'Backup fail fixture (checksum mismatch)',
                path: OpsScenarioSeeder::failChecksumBackupPath(),
                description: 'Fixture fail-path khi checksum artifact bị lệch.',
            ),
        ];
        $readinessFixtures = [
            $this->inspectReadinessReport(
                label: 'Readiness pass fixture',
                path: OpsScenarioSeeder::passReadinessReportPath(),
                description: 'Fixture report pass đầy đủ gate để QA/PM verify local.',
            ),
            $this->inspectReadinessReport(
                label: 'Readiness strict-fail fixture',
                path: OpsScenarioSeeder::failStrictReadinessReportPath(),
                description: 'Fixture fail-path khi report không đạt strict deploy requirements.',
            ),
            $this->inspectReadinessReport(
                label: 'Readiness invalid-schema fixture',
                path: OpsScenarioSeeder::invalidSchemaReadinessReportPath(),
                description: 'Fixture fail-path khi schema report không hợp lệ.',
            ),
        ];
        $signoffFixtures = [
            $this->inspectReadinessSignoff(
                label: 'Readiness signoff fixture',
                path: OpsScenarioSeeder::passReadinessSignoffPath(),
                description: 'Artifact sign-off local để admin/manager thấy contract deploy hoàn chỉnh.',
            ),
        ];
        $latestRuntimeReport = $this->latestJsonArtifact(
            label: 'Latest runtime readiness report',
            directory: storage_path('app/release-readiness'),
            description: 'Artifact report mới nhất sinh từ ops:run-production-readiness.',
            excludeDirectories: ['signoff'],
            inspector: fn (string $path, string $label, string $description): array => $this->inspectReadinessReport($label, $path, $description),
        );
        $latestRuntimeSignoff = $this->latestJsonArtifact(
            label: 'Latest runtime readiness signoff',
            directory: storage_path('app/release-readiness/signoff'),
            description: 'Artifact sign-off mới nhất sinh từ ops:verify-production-readiness-report.',
            inspector: fn (string $path, string $label, string $description): array => $this->inspectReadinessSignoff($label, $path, $description),
        );
        $observability = $this->observabilitySummary();
        $integrations = $this->integrationOperationsSummary();
        $kpi = $this->kpiOperationsSummary();
        $finance = $this->financeOperationsSummary();
        $governance = $this->governanceOperationsSummary();
        $zns = $this->znsOperationsSummary();
        $recentRuns = $this->recentOpsRuns();

        return [
            'overview_cards' => [
                $automationActor['card'],
                $this->buildIntegrationOverviewCard($integrations),
                $this->buildKpiOverviewCard($kpi),
                $this->buildFinanceOverviewCard($finance),
                $this->buildGovernanceOverviewCard($governance),
                $this->buildZnsOverviewCard($zns),
                $this->buildBackupOverviewCard($runtimeBackup, $backupFixtures),
                $this->buildReadinessOverviewCard($latestRuntimeReport, $latestRuntimeSignoff, $readinessFixtures, $signoffFixtures),
                $this->buildObservabilityOverviewCard($observability),
            ],
            'automation_actor' => $automationActor,
            'integrations' => $integrations,
            'kpi' => $kpi,
            'finance' => $finance,
            'governance' => $governance,
            'zns' => $zns,
            'runtime_backup' => $runtimeBackup,
            'backup_fixtures' => $backupFixtures,
            'readiness_fixtures' => $readinessFixtures,
            'signoff_fixtures' => $signoffFixtures,
            'latest_runtime_report' => $latestRuntimeReport,
            'latest_runtime_signoff' => $latestRuntimeSignoff,
            'observability' => $observability,
            'recent_runs' => $recentRuns,
            'smoke_commands' => $this->smokeCommands(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function automationActorSummary(): array
    {
        $report = $this->automationActorResolver->healthReport(
            permission: ActionPermission::AUTOMATION_RUN,
            enforceRequiredRole: true,
        );
        $issues = collect((array) ($report['issues'] ?? []));
        $actor = $report['actor'] ?? null;
        $tone = $this->toneFromIssues($issues);
        $label = $actor?->email ?: 'Chưa cấu hình';
        $meta = [
            [
                'label' => 'Required role',
                'value' => (string) ($report['required_role'] ?? '-'),
            ],
            [
                'label' => 'Permission',
                'value' => (string) ($report['permission'] ?? ActionPermission::AUTOMATION_RUN),
            ],
            [
                'label' => 'Roles',
                'value' => collect((array) ($report['roles'] ?? []))->implode(', ') ?: '(none)',
            ],
        ];

        return [
            'tone' => $tone,
            'status' => $this->toneLabel($tone),
            'label' => $label,
            'meta' => $meta,
            'issues' => $issues->map(fn (array $issue): array => [
                'severity' => (string) ($issue['severity'] ?? 'info'),
                'code' => (string) ($issue['code'] ?? 'unknown'),
                'message' => (string) ($issue['message'] ?? ''),
            ])->values()->all(),
            'card' => [
                'title' => 'Scheduler automation actor',
                'value' => $label,
                'description' => 'Service account chạy scheduler và các command automation nhạy cảm.',
                'tone' => $tone,
                'status' => $this->toneLabel($tone),
                'meta' => [
                    'Required role '.((string) ($report['required_role'] ?? '-')),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectBackupDirectory(string $label, string $path, string $description): array
    {
        $directoryExists = is_dir($path);
        $latestManifest = $this->manifestService->latest($path);
        $manifestPath = is_array($latestManifest) ? ($latestManifest['path'] ?? null) : null;
        $manifest = is_array($latestManifest) ? ($latestManifest['data'] ?? null) : null;
        $validationErrors = $this->manifestService->validate(is_array($manifest) ? $manifest : null);
        $artifactPath = is_array($manifest) ? $this->manifestService->artifactPath($path, $manifest) : null;
        $artifactExists = is_string($artifactPath) && File::exists($artifactPath);
        $artifactChecksum = $artifactExists && is_string($artifactPath)
            ? $this->manifestService->checksum($artifactPath)
            : null;
        $expectedChecksum = is_array($manifest) ? (string) ($manifest['artifact_checksum_sha256'] ?? '') : '';
        $checksumValid = $artifactChecksum !== null && $expectedChecksum !== '' && hash_equals($expectedChecksum, $artifactChecksum);
        $artifactSizeBytes = $artifactExists && is_string($artifactPath) ? (int) File::size($artifactPath) : 0;
        $expectedArtifactSize = is_array($manifest) ? (int) ($manifest['artifact_size_bytes'] ?? 0) : 0;
        $sizeValid = $artifactExists && $artifactSizeBytes > 0 && $expectedArtifactSize > 0 && $artifactSizeBytes === $expectedArtifactSize;
        $createdAt = is_array($manifest) ? $this->manifestService->createdAt($manifest) : null;
        $latestAgeHours = $createdAt?->diffInHours(now(), absolute: true);
        $healthy = $directoryExists
            && $manifestPath !== null
            && $validationErrors === []
            && $artifactExists
            && $sizeValid
            && $checksumValid;
        $tone = $healthy ? 'success' : 'danger';
        $status = $healthy ? 'Healthy' : 'Unhealthy';
        $error = $healthy ? null : $this->backupErrorCode(
            directoryExists: $directoryExists,
            manifestPath: $manifestPath,
            validationErrors: $validationErrors,
            artifactExists: $artifactExists,
            sizeValid: $sizeValid,
            checksumValid: $checksumValid,
        );

        return [
            'label' => $label,
            'description' => $description,
            'path' => $path,
            'tone' => $tone,
            'status' => $status,
            'error' => $error,
            'meta' => [
                [
                    'label' => 'Manifest',
                    'value' => is_string($manifestPath) ? basename($manifestPath) : '-',
                ],
                [
                    'label' => 'Artifact',
                    'value' => $artifactExists && is_string($artifactPath) ? basename($artifactPath) : '-',
                ],
                [
                    'label' => 'Age',
                    'value' => $latestAgeHours !== null ? $latestAgeHours.'h' : '-',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectReadinessReport(string $label, string $path, string $description): array
    {
        if (! is_file($path)) {
            return [
                'label' => $label,
                'description' => $description,
                'path' => $path,
                'tone' => 'danger',
                'status' => 'Missing',
                'error' => 'report_missing',
                'meta' => [],
                'report' => null,
            ];
        }

        $decoded = $this->decodeJsonFile($path);

        if (! is_array($decoded)) {
            return [
                'label' => $label,
                'description' => $description,
                'path' => $path,
                'tone' => 'danger',
                'status' => 'Invalid JSON',
                'error' => 'report_invalid_json',
                'meta' => [],
                'report' => null,
            ];
        }

        $reportStatus = (string) ($decoded['status'] ?? 'unknown');
        $tone = match ($reportStatus) {
            'pass' => 'success',
            'dry_run' => 'warning',
            'fail' => 'danger',
            default => 'warning',
        };

        return [
            'label' => $label,
            'description' => $description,
            'path' => $path,
            'tone' => $tone,
            'status' => strtoupper($reportStatus),
            'error' => null,
            'meta' => [
                [
                    'label' => 'Finished at',
                    'value' => (string) ($decoded['finished_at'] ?? '-'),
                ],
                [
                    'label' => 'Strict full',
                    'value' => ((bool) ($decoded['strict_full'] ?? false)) ? 'true' : 'false',
                ],
                [
                    'label' => 'With finance',
                    'value' => ((bool) ($decoded['with_finance'] ?? false)) ? 'true' : 'false',
                ],
                [
                    'label' => 'Run tests',
                    'value' => ((bool) ($decoded['run_tests'] ?? false)) ? 'true' : 'false',
                ],
            ],
            'report' => $decoded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectReadinessSignoff(string $label, string $path, string $description): array
    {
        if (! is_file($path)) {
            return [
                'label' => $label,
                'description' => $description,
                'path' => $path,
                'tone' => 'warning',
                'status' => 'Not generated',
                'error' => 'signoff_missing',
                'meta' => [],
                'signoff' => null,
            ];
        }

        $decoded = $this->decodeJsonFile($path);

        if (! is_array($decoded)) {
            return [
                'label' => $label,
                'description' => $description,
                'path' => $path,
                'tone' => 'danger',
                'status' => 'Invalid JSON',
                'error' => 'signoff_invalid_json',
                'meta' => [],
                'signoff' => null,
            ];
        }

        $qaEmail = (string) data_get($decoded, 'qa_signoff.email', '');
        $pmEmail = (string) data_get($decoded, 'pm_signoff.email', '');
        $reportStatus = (string) ($decoded['report_status'] ?? '');
        $verifiedAt = (string) ($decoded['verified_at'] ?? '-');
        $tone = ($reportStatus === 'pass' && $qaEmail !== '' && $pmEmail !== '') ? 'success' : 'warning';

        return [
            'label' => $label,
            'description' => $description,
            'path' => $path,
            'tone' => $tone,
            'status' => $reportStatus !== '' ? strtoupper($reportStatus) : 'UNKNOWN',
            'error' => null,
            'meta' => [
                [
                    'label' => 'Verified at',
                    'value' => $verifiedAt,
                ],
                [
                    'label' => 'QA',
                    'value' => $qaEmail !== '' ? $qaEmail : '-',
                ],
                [
                    'label' => 'PM',
                    'value' => $pmEmail !== '' ? $pmEmail : '-',
                ],
                [
                    'label' => 'Release ref',
                    'value' => (string) ($decoded['release_ref'] ?? '-'),
                ],
            ],
            'signoff' => $decoded,
        ];
    }

    /**
     * @param  callable(string, string, string): array<string, mixed>  $inspector
     * @param  array<int, string>  $excludeDirectories
     * @return array<string, mixed>|null
     */
    protected function latestJsonArtifact(
        string $label,
        string $directory,
        string $description,
        callable $inspector,
        array $excludeDirectories = [],
    ): ?array {
        if (! is_dir($directory)) {
            return null;
        }

        $files = collect(File::allFiles($directory))
            ->filter(function (\SplFileInfo $file) use ($excludeDirectories): bool {
                if ($file->getExtension() !== 'json') {
                    return false;
                }

                $pathname = $file->getPathname();

                foreach ($excludeDirectories as $excludedDirectory) {
                    if (str_contains($pathname, DIRECTORY_SEPARATOR.$excludedDirectory.DIRECTORY_SEPARATOR)) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->values();

        $latest = $files->first();

        if (! $latest instanceof \SplFileInfo) {
            return null;
        }

        return $inspector($latest->getPathname(), $label, $description);
    }

    /**
     * @return array<string, mixed>
     */
    protected function integrationOperationsSummary(): array
    {
        $providerCards = $this->integrationProviderHealthReadModelService->cards();
        $providerCounts = $this->integrationProviderHealthReadModelService->counts();
        $activeGraceRotations = $this->integrationOperationalReadModelService
            ->activeGraceRotations()
            ->map(fn (array $rotation): array => [
                'display_name' => (string) ($rotation['display_name'] ?? 'Integration secret'),
                'grace_expires_at' => $this->formatDateTime(data_get($rotation, 'grace_expires_at')),
                'remaining_minutes' => (int) ($rotation['remaining_minutes'] ?? 0),
            ])
            ->all();

        $expiredGraceRotations = $this->integrationOperationalReadModelService
            ->expiredGraceRotations()
            ->map(fn (array $rotation): array => [
                'display_name' => (string) ($rotation['display_name'] ?? 'Integration secret'),
                'grace_expires_at' => $this->formatDateTime(data_get($rotation, 'grace_expires_at')),
                'expired_minutes' => (int) ($rotation['expired_minutes'] ?? 0),
            ])
            ->all();

        $retentionCandidates = [
            $this->integrationRetentionCandidate(
                label: 'Web lead ingestion',
                retentionDays: ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
                total: $this->integrationOperationalReadModelService->webLeadIngestionRetentionCandidateCount(
                    ClinicRuntimeSettings::webLeadOperationalRetentionDays()
                ),
                description: 'Lead ingestion log quá hạn retention.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Web lead internal email deliveries',
                retentionDays: ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
                total: $this->integrationOperationalReadModelService->webLeadTerminalEmailRetentionCandidateCount(
                    ClinicRuntimeSettings::webLeadOperationalRetentionDays()
                ),
                description: 'Delivery log email nội bộ đã terminal và quá hạn review window.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Zalo webhook',
                retentionDays: ClinicRuntimeSettings::zaloWebhookRetentionDays(),
                total: $this->integrationOperationalReadModelService->zaloWebhookRetentionCandidateCount(
                    ClinicRuntimeSettings::zaloWebhookRetentionDays()
                ),
                description: 'Webhook inbound đã quá hạn retention.',
            ),
            $this->integrationRetentionCandidate(
                label: 'EMR outbox',
                retentionDays: ClinicRuntimeSettings::emrOperationalRetentionDays(),
                total: $this->integrationOperationalReadModelService->emrRetentionCandidateCount(
                    ClinicRuntimeSettings::emrOperationalRetentionDays()
                ),
                description: 'EMR log + event đã đồng bộ xong và đủ điều kiện prune.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Clinical media temporary',
                retentionDays: ClinicRuntimeSettings::clinicalMediaRetentionDays(\App\Models\ClinicalMediaAsset::RETENTION_TEMPORARY),
                total: ClinicRuntimeSettings::clinicalMediaRetentionEnabled()
                    ? $this->integrationOperationalReadModelService->clinicalMediaRetentionCandidateCount(
                        \App\Models\ClinicalMediaAsset::RETENTION_TEMPORARY,
                        ClinicRuntimeSettings::clinicalMediaRetentionDays(\App\Models\ClinicalMediaAsset::RETENTION_TEMPORARY)
                    )
                    : 0,
                description: 'Clinical media retention class temporary đã đủ điều kiện prune.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Clinical media operational',
                retentionDays: ClinicRuntimeSettings::clinicalMediaRetentionDays(\App\Models\ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL),
                total: ClinicRuntimeSettings::clinicalMediaRetentionEnabled()
                    ? $this->integrationOperationalReadModelService->clinicalMediaRetentionCandidateCount(
                        \App\Models\ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
                        ClinicRuntimeSettings::clinicalMediaRetentionDays(\App\Models\ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL)
                    )
                    : 0,
                description: 'Clinical media retention class clinical_operational đã đủ điều kiện prune.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Google Calendar outbox',
                retentionDays: ClinicRuntimeSettings::googleCalendarOperationalRetentionDays(),
                total: $this->integrationOperationalReadModelService->googleCalendarRetentionCandidateCount(
                    ClinicRuntimeSettings::googleCalendarOperationalRetentionDays()
                ),
                description: 'Google Calendar log + event đã đủ điều kiện prune.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Popup announcement logs',
                retentionDays: ClinicRuntimeSettings::popupAnnouncementRetentionDays(),
                total: $this->integrationOperationalReadModelService->popupDeliveryRetentionCandidateCount(
                    ClinicRuntimeSettings::popupAnnouncementRetentionDays()
                ) + $this->integrationOperationalReadModelService->popupAnnouncementRetentionCandidateCount(
                    ClinicRuntimeSettings::popupAnnouncementRetentionDays()
                ),
                description: 'Popup delivery và announcement log đã quá hạn retention.',
            ),
            $this->integrationRetentionCandidate(
                label: 'Patient photos',
                retentionDays: ClinicRuntimeSettings::patientPhotoRetentionDays(),
                total: ClinicRuntimeSettings::patientPhotoRetentionEnabled()
                    ? $this->integrationOperationalReadModelService->patientPhotoRetentionCandidateCount(
                        ClinicRuntimeSettings::patientPhotoRetentionDays(),
                        ClinicRuntimeSettings::patientPhotoRetentionIncludeXray()
                    )
                    : 0,
                description: 'Ảnh bệnh nhân đã quá hạn retention theo runtime setting hiện tại.',
            ),
        ];

        $expiredCount = count($expiredGraceRotations);
        $activeCount = count($activeGraceRotations);
        $retentionBacklog = collect($retentionCandidates)->sum('total');
        $providerDriftCount = (int) ($providerCounts['degraded'] ?? 0);
        $tone = ($expiredCount > 0 || $providerDriftCount > 0)
            ? 'danger'
            : (($activeCount > 0 || $retentionBacklog > 0) ? 'warning' : 'success');
        $status = $expiredCount > 0
            ? 'Expired grace tokens'
            : ($providerDriftCount > 0
                ? 'Provider config drift'
                : (($activeCount > 0 || $retentionBacklog > 0) ? 'Needs review' : 'Healthy'));

        return [
            'tone' => $tone,
            'status' => $status,
            'meta' => [
                [
                    'label' => 'Active grace',
                    'value' => $activeCount,
                ],
                [
                    'label' => 'Expired grace',
                    'value' => $expiredCount,
                ],
                [
                    'label' => 'Prune backlog',
                    'value' => $retentionBacklog,
                ],
                [
                    'label' => 'Provider healthy',
                    'value' => (int) ($providerCounts['healthy'] ?? 0),
                ],
                [
                    'label' => 'Provider drift',
                    'value' => $providerDriftCount,
                ],
                [
                    'label' => 'Lead mail retryable',
                    'value' => $this->integrationOperationalReadModelService->webLeadRetryableEmailCount(),
                ],
                [
                    'label' => 'Lead mail dead',
                    'value' => $this->integrationOperationalReadModelService->webLeadDeadEmailCount(),
                ],
            ],
            'providers' => $providerCards,
            'provider_counts' => $providerCounts,
            'active_grace_rotations' => $activeGraceRotations,
            'expired_grace_rotations' => $expiredGraceRotations,
            'retention_candidates' => $retentionCandidates,
            'links' => [
                [
                    'label' => 'Cài đặt tích hợp',
                    'description' => 'Runtime settings, secret rotation và audit log integration.',
                    'url' => IntegrationSettings::getUrl(),
                ],
                [
                    'label' => 'Delivery mail web lead',
                    'description' => 'Triage backlog mail nội bộ, resend và theo dõi trạng thái gửi.',
                    'url' => WebLeadEmailDeliveryResource::getUrl('index'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function znsOperationsSummary(): array
    {
        $summaryCards = $this->znsOperationalReadModelService->summaryCards();

        $retentionDays = ClinicRuntimeSettings::znsOperationalRetentionDays();
        $retentionCandidates = [
            $this->integrationRetentionCandidate(
                label: 'ZNS automation logs',
                retentionDays: $retentionDays,
                total: $this->znsOperationalReadModelService->automationLogRetentionCandidateCount($retentionDays),
                description: 'Log automation đủ điều kiện prune.',
            ),
            $this->integrationRetentionCandidate(
                label: 'ZNS automation events',
                retentionDays: $retentionDays,
                total: $this->znsOperationalReadModelService->automationRetentionCandidateCount($retentionDays),
                description: 'Event SENT/DEAD đã quá hạn retention.',
            ),
            $this->integrationRetentionCandidate(
                label: 'ZNS deliveries',
                retentionDays: $retentionDays,
                total: $this->znsOperationalReadModelService->deliveryRetentionCandidateCount($retentionDays),
                description: 'Delivery sent/skipped hoặc failed terminal đủ điều kiện prune.',
            ),
        ];

        $hasDeadBacklog = collect($summaryCards)
            ->whereIn('label', ['Automation dead-letter', 'Delivery terminal failed', 'Campaign failed'])
            ->sum('value') > 0;
        $hasRetryBacklog = collect($summaryCards)
            ->whereIn('label', ['Automation pending', 'Automation retry due', 'Delivery retry due'])
            ->sum('value') > 0;
        $tone = $hasDeadBacklog ? 'danger' : ($hasRetryBacklog ? 'warning' : 'success');
        $status = $hasDeadBacklog ? 'Needs triage' : ($hasRetryBacklog ? 'Backlog active' : 'Healthy');

        return [
            'tone' => $tone,
            'status' => $status,
            'summary_cards' => $summaryCards,
            'retention_candidates' => $retentionCandidates,
            'links' => [
                [
                    'label' => 'Zalo ZNS triage',
                    'description' => 'Backlog automation và retry/dead-letter view.',
                    'url' => ZaloZns::getUrl(),
                ],
                [
                    'label' => 'Danh sách campaign',
                    'description' => 'Mở workflow campaign và delivery relation manager.',
                    'url' => ZnsCampaignResource::getUrl('index'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function kpiOperationsSummary(): array
    {
        $branchIds = $this->operatorBranchIds();
        $snapshotDate = $this->latestOperationalKpiSnapshotDate($branchIds);
        $snapshots = $this->operationalKpiSnapshotReadModelService
            ->snapshotsForDate($snapshotDate, $branchIds, ['id', 'branch_scope_id', 'sla_status', 'generated_at']);

        $snapshotCounts = $this->operationalKpiSnapshotReadModelService
            ->snapshotCounts($snapshots, $branchIds);

        $alertSummary = $this->operationalKpiAlertReadModelService
            ->opsSummaryForDate($snapshotDate, $branchIds);

        $aggregateReadiness = [
            [
                'label' => 'Revenue aggregate',
                'ready' => $this->hotReportAggregateReadinessService->shouldUseRevenueAggregate($branchIds, $snapshotDate, $snapshotDate),
            ],
            [
                'label' => 'Care aggregate',
                'ready' => $this->hotReportAggregateReadinessService->shouldUseCareAggregate($branchIds, $snapshotDate, $snapshotDate),
            ],
        ];

        $tone = $snapshotCounts['missing'] > 0 || $snapshotCounts['stale'] > 0
            ? 'danger'
            : (($alertSummary['open_count'] > 0 || collect($aggregateReadiness)->contains(fn (array $item): bool => ! $item['ready'])) ? 'warning' : 'success');
        $status = $snapshotCounts['missing'] > 0
            ? 'Snapshot gaps detected'
            : (($snapshotCounts['stale'] > 0 || $alertSummary['open_count'] > 0) ? 'Needs triage' : 'Healthy');

        return [
            'tone' => $tone,
            'status' => $status,
            'snapshot_date' => $snapshotDate,
            'meta' => [
                [
                    'label' => 'Visible branches',
                    'value' => count($branchIds),
                ],
                [
                    'label' => 'Open alerts',
                    'value' => $alertSummary['open_count'],
                ],
                [
                    'label' => 'Resolved alerts',
                    'value' => $alertSummary['resolved_count'],
                ],
            ],
            'snapshot_counts' => $snapshotCounts,
            'aggregate_readiness' => $aggregateReadiness,
            'open_alerts' => $alertSummary['open_alerts'],
            'links' => [
                [
                    'label' => 'KPI vận hành',
                    'description' => 'Snapshot pack, SLA và alert count theo chi nhánh.',
                    'url' => OperationalKpiPack::getUrl(),
                ],
                [
                    'label' => 'Doanh thu',
                    'description' => 'Hot revenue report với freshness gate aggregate/raw.',
                    'url' => RevenueStatistical::getUrl(),
                ],
                [
                    'label' => 'CSKH',
                    'description' => 'Care queue aggregate theo chi nhánh.',
                    'url' => CustomsCareStatistical::getUrl(),
                ],
                [
                    'label' => 'Risk dashboard',
                    'description' => 'No-show/churn triage linked to care workload.',
                    'url' => RiskScoringDashboard::getUrl(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function financeOperationsSummary(): array
    {
        $branchIds = $this->operatorBranchIds();
        $needsOverdueSyncCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIAL,
            ])
            ->count();
        $overdueCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->where('status', Invoice::STATUS_OVERDUE)
            ->count();
        $partialCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->where('status', Invoice::STATUS_PARTIAL)
            ->count();
        $dunningCandidateCount = $this->applyBranchScope(
            InstallmentPlan::query(),
            $branchIds,
        )
            ->whereIn('status', [
                InstallmentPlan::STATUS_ACTIVE,
                InstallmentPlan::STATUS_DEFAULTED,
            ])
            ->whereDate('next_due_date', '<', today())
            ->count();
        $reversibleReceiptCount = $this->applyBranchScope(
            Payment::query(),
            $branchIds,
        )
            ->where('direction', 'receipt')
            ->whereNull('reversal_of_id')
            ->whereNull('reversed_at')
            ->count();

        $overdueScenarioInvoice = $this->applyBranchScope(
            Invoice::query()->with(['patient:id,full_name,patient_code', 'branch:id,name']),
            $branchIds,
        )
            ->where('invoice_no', FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
            ->first();
        $reversalScenarioPayment = $this->applyBranchScope(
            Payment::query()->with(['invoice.patient:id,full_name,patient_code', 'branch:id,name']),
            $branchIds,
        )
            ->where('transaction_ref', FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
            ->first();
        $installmentScenarioPlan = $this->applyBranchScope(
            InstallmentPlan::query()->with(['patient:id,full_name,patient_code', 'branch:id,name']),
            $branchIds,
        )
            ->where('plan_code', FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
            ->first();

        $tone = $needsOverdueSyncCount > 0
            ? 'danger'
            : (($dunningCandidateCount > 0 || $overdueCount > 0) ? 'warning' : 'success');
        $status = $needsOverdueSyncCount > 0
            ? 'Needs aging sync'
            : (($dunningCandidateCount > 0 || $overdueCount > 0) ? 'Collections backlog' : 'Healthy');

        return [
            'tone' => $tone,
            'status' => $status,
            'meta' => [
                [
                    'label' => 'Visible branches',
                    'value' => count($branchIds),
                ],
                [
                    'label' => 'Needs overdue sync',
                    'value' => $needsOverdueSyncCount,
                ],
                [
                    'label' => 'Overdue invoices',
                    'value' => $overdueCount,
                ],
                [
                    'label' => 'Dunning candidates',
                    'value' => $dunningCandidateCount,
                ],
            ],
            'signals' => [
                [
                    'label' => 'Issued / partial past due',
                    'value' => $needsOverdueSyncCount,
                    'tone' => $needsOverdueSyncCount > 0 ? 'danger' : 'success',
                ],
                [
                    'label' => 'Partial invoices',
                    'value' => $partialCount,
                    'tone' => $partialCount > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Installment dunning',
                    'value' => $dunningCandidateCount,
                    'tone' => $dunningCandidateCount > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Receipts reversible',
                    'value' => $reversibleReceiptCount,
                    'tone' => $reversibleReceiptCount > 0 ? 'info' : 'success',
                ],
            ],
            'watchlist' => collect([
                $overdueScenarioInvoice instanceof Invoice ? [
                    'title' => FinanceScenarioSeeder::OVERDUE_INVOICE_NO,
                    'subtitle' => $overdueScenarioInvoice->patient?->full_name ?: 'Overdue invoice',
                    'detail' => ($overdueScenarioInvoice->branch?->name ?: '-').' · due '.$this->formatDateTime($overdueScenarioInvoice->due_date),
                    'badge' => 'Needs sync',
                    'tone' => 'danger',
                ] : null,
                $reversalScenarioPayment instanceof Payment ? [
                    'title' => FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF,
                    'subtitle' => $reversalScenarioPayment->invoice?->patient?->full_name ?: 'Receipt reversal smoke',
                    'detail' => ($reversalScenarioPayment->branch?->name ?: '-').' · '.$this->formatMoney($reversalScenarioPayment->amount),
                    'badge' => 'Reversible receipt',
                    'tone' => 'warning',
                ] : null,
                $installmentScenarioPlan instanceof InstallmentPlan ? [
                    'title' => FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE,
                    'subtitle' => $installmentScenarioPlan->patient?->full_name ?: 'Installment dunning',
                    'detail' => ($installmentScenarioPlan->branch?->name ?: '-').' · next due '.$this->formatDateTime($installmentScenarioPlan->next_due_date),
                    'badge' => 'Collections watch',
                    'tone' => 'warning',
                ] : null,
            ])
                ->filter()
                ->values()
                ->all(),
            'links' => collect([
                [
                    'label' => 'Dashboard tài chính',
                    'description' => 'Quick stats, overdue widget và branch finance overview.',
                    'url' => FinancialDashboard::getUrl(),
                    'can' => FinancialDashboard::canAccess(),
                ],
                [
                    'label' => 'Hóa đơn',
                    'description' => 'Invoice aging, overdue sync và branch-scoped collections.',
                    'url' => InvoiceResource::getUrl('index'),
                    'can' => InvoiceResource::canAccess(),
                ],
                [
                    'label' => 'Thanh toán',
                    'description' => 'Receipt/refund history và reversal workflow.',
                    'url' => PaymentResource::getUrl('index'),
                    'can' => PaymentResource::canAccess(),
                ],
                [
                    'label' => 'Thu/chi',
                    'description' => 'Phiếu thu/chi manual cho branch hiện tại.',
                    'url' => ReceiptsExpenseResource::getUrl('index'),
                    'can' => ReceiptsExpenseResource::canAccess(),
                ],
            ])
                ->filter(fn (array $link): bool => (bool) ($link['can'] ?? false))
                ->map(fn (array $link): array => [
                    'label' => $link['label'],
                    'description' => $link['description'],
                    'url' => $link['url'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function governanceOperationsSummary(): array
    {
        $authUser = $this->currentUser();
        $branchIds = $this->operatorBranchIds();
        $canViewUsers = UserResource::canAccess();
        $canViewAudit = AuditLogResource::canAccess();

        $visibleUserCount = null;
        $crossBranchDoctorCount = null;
        $scenarioUsers = [];
        $recentAudits = [];

        if ($authUser instanceof User && $canViewUsers) {
            $visibleUserCount = User::query()
                ->visibleTo($authUser)
                ->count();
            $crossBranchDoctorCount = User::query()
                ->visibleTo($authUser)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Doctor'))
                ->whereHas('activeDoctorBranchAssignments')
                ->count();
            $scenarioUsers = User::query()
                ->visibleTo($authUser)
                ->with(['branch:id,name', 'roles:id,name', 'activeDoctorBranchAssignments.branch:id,name'])
                ->whereIn('email', [
                    GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL,
                    GovernanceScenarioSeeder::HIDDEN_USER_EMAIL,
                ])
                ->orderBy('email')
                ->get()
                ->map(function (User $user): array {
                    return [
                        'email' => $user->email,
                        'role' => $user->roles->pluck('name')->implode(', ') ?: 'No role',
                        'branch' => $user->branch?->name ?: '-',
                        'assignments' => $user->activeDoctorBranchAssignments
                            ->pluck('branch.name')
                            ->filter()
                            ->implode(', '),
                    ];
                })
                ->all();
        }

        if ($authUser instanceof User && $canViewAudit) {
            $recentAudits = $this->governanceAuditReadModelService
                ->recentAudits($authUser, 5)
                ->map(function (AuditLog $auditLog): array {
                    return [
                        'entity' => $auditLog->entity_type,
                        'action' => $auditLog->action,
                        'actor' => $auditLog->actor?->email ?: 'system',
                        'occurred_at' => $this->formatDateTime($auditLog->occurred_at),
                    ];
                })
                ->all();
        }

        $tone = ($canViewUsers || $canViewAudit) ? 'success' : 'info';
        $status = ($canViewUsers || $canViewAudit)
            ? 'Admin scope available'
            : 'Role-limited overview';

        return [
            'tone' => $tone,
            'status' => $status,
            'policy_note' => ($canViewUsers || $canViewAudit)
                ? 'Admin có thể kiểm tra user directory và audit scope ngay từ cockpit này.'
                : 'Governance resource giữ admin-only theo baseline role matrix hiện tại.',
            'meta' => [
                [
                    'label' => 'Visible branches',
                    'value' => count($branchIds),
                ],
                [
                    'label' => 'User directory',
                    'value' => $canViewUsers ? 'Allowed' : 'Restricted',
                ],
                [
                    'label' => 'Audit log',
                    'value' => $canViewAudit ? 'Allowed' : 'Restricted',
                ],
                [
                    'label' => 'Visible users',
                    'value' => $visibleUserCount ?? 'Admin only',
                ],
            ],
            'signals' => [
                [
                    'label' => 'Cross-branch doctors',
                    'value' => $crossBranchDoctorCount ?? 'Admin only',
                    'tone' => $crossBranchDoctorCount === null
                        ? 'info'
                        : ($crossBranchDoctorCount > 0 ? 'warning' : 'success'),
                ],
                [
                    'label' => 'Scenario users visible',
                    'value' => $canViewUsers ? count($scenarioUsers) : 'Admin only',
                    'tone' => $canViewUsers
                        ? (count($scenarioUsers) > 0 ? 'success' : 'warning')
                        : 'info',
                ],
                [
                    'label' => 'Recent audits',
                    'value' => $canViewAudit ? count($recentAudits) : 'Admin only',
                    'tone' => $canViewAudit
                        ? (count($recentAudits) > 0 ? 'success' : 'warning')
                        : 'info',
                ],
            ],
            'scenario_users' => $scenarioUsers,
            'recent_audits' => $recentAudits,
            'links' => collect([
                [
                    'label' => 'Người dùng',
                    'description' => 'Branch-scoped user visibility và doctor assignments.',
                    'url' => UserResource::getUrl('index'),
                    'can' => $canViewUsers,
                ],
                [
                    'label' => 'Audit log',
                    'description' => 'Security, automation, transfer và finance audit scope.',
                    'url' => AuditLogResource::getUrl('index'),
                    'can' => $canViewAudit,
                ],
            ])
                ->filter(fn (array $link): bool => (bool) ($link['can'] ?? false))
                ->map(fn (array $link): array => [
                    'label' => $link['label'],
                    'description' => $link['description'],
                    'url' => $link['url'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function observabilitySummary(): array
    {
        $windowHours = ClinicRuntimeSettings::observabilityWindowHours();
        $snapshotDate = now()->subDay()->toDateString();
        $windowStartedAt = now()->subHours($windowHours);
        $runbookMap = ClinicRuntimeSettings::opsAlertRunbookMap();

        $metrics = [
            'dead_backlog_total' => $this->integrationOperationalReadModelService->googleCalendarDeadBacklogCount()
                + $this->integrationOperationalReadModelService->emrDeadBacklogCount()
                + $this->znsOperationalReadModelService->automationDeadCount(),
            'retryable_failed_backlog_total' => $this->integrationOperationalReadModelService->googleCalendarFailedBacklogCount()
                + $this->integrationOperationalReadModelService->emrFailedBacklogCount()
                + $this->znsOperationalReadModelService->automationFailedCount(),
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
                return trim((string) data_get($runbookMap, "{$category}.owner_role", '')) === ''
                    || trim((string) data_get($runbookMap, "{$category}.threshold", '')) === ''
                    || trim((string) data_get($runbookMap, "{$category}.runbook", '')) === '';
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
            ->map(function (int $budget, string $metric) use ($metrics): ?array {
                $value = (int) ($metrics[$metric] ?? 0);

                if ($value <= $budget) {
                    return null;
                }

                return [
                    'metric' => $metric,
                    'value' => $value,
                    'budget' => $budget,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $tone = $breaches === [] ? 'success' : 'warning';

        return [
            'tone' => $tone,
            'status' => $breaches === [] ? 'Healthy' : 'Attention',
            'window_hours' => $windowHours,
            'snapshot_date' => $snapshotDate,
            'metrics' => collect($metrics)
                ->map(fn (int $value, string $metric): array => [
                    'label' => $this->observabilityLabel($metric),
                    'key' => $metric,
                    'value' => $value,
                    'budget' => $budgets[$metric] ?? 0,
                ])
                ->values()
                ->all(),
            'breaches' => $breaches,
            'missing_runbook_categories' => $missingRunbookCategories,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentOpsRuns(): array
    {
        return $this->operationalAutomationAuditReadModelService
            ->recentRuns(12)
            ->map(function (AuditLog $auditLog): array {
                $command = (string) data_get($auditLog->metadata, 'command', data_get($auditLog->metadata, 'channel', 'ops'));
                $status = (string) data_get($auditLog->metadata, 'status', $auditLog->action);
                $summary = (string) (
                    data_get($auditLog->metadata, 'error')
                    ?: data_get($auditLog->metadata, 'error_code')
                    ?: data_get($auditLog->metadata, 'runbook_category')
                    ?: '-'
                );

                return [
                    'command' => $command,
                    'status' => $status,
                    'action' => $auditLog->action,
                    'actor' => $auditLog->actor?->email ?: 'system',
                    'occurred_at' => $this->formatDateTime($auditLog->occurred_at),
                    'summary' => $summary,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function smokeCommands(): array
    {
        return [
            'php artisan ops:check-backup-health --path="'.OpsScenarioSeeder::readyBackupPath().'" --strict',
            'php artisan ops:check-backup-health --path="'.OpsScenarioSeeder::failMissingManifestBackupPath().'" --strict',
            'php artisan ops:run-restore-drill --path="'.OpsScenarioSeeder::readyBackupPath().'" --strict',
            'php artisan ops:verify-production-readiness-report "'.OpsScenarioSeeder::passReadinessReportPath().'" --qa=manager.q1@demo.ident.test --pm=admin@demo.ident.test --release-ref=REL-DEMO-OPS-001 --strict',
            'php artisan ops:check-observability-health --strict',
            'php artisan integrations:revoke-rotated-secrets --dry-run --strict',
            'php artisan integrations:prune-operational-data --dry-run --strict',
            'php artisan emr:reconcile-integrity --strict',
            'php artisan emr:reconcile-clinical-media --strict',
            'php artisan emr:check-dicom-readiness --strict',
            'php artisan emr:prune-clinical-media --dry-run --strict',
            'php artisan google-calendar:sync-events --dry-run --strict-exit',
            'php artisan reports:check-snapshot-sla --date='.KpiScenarioSeeder::snapshotDate().' --dry-run',
            'php artisan reports:snapshot-hot-aggregates --date='.KpiScenarioSeeder::snapshotDate().' --dry-run',
            'php artisan zns:sync-automation-events --dry-run --strict-exit',
            'php artisan zns:prune-operational-data --dry-run --strict',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function integrationRetentionCandidate(
        string $label,
        int $retentionDays,
        int $total,
        string $description,
    ): array {
        return [
            'label' => $label,
            'retention_days' => $retentionDays,
            'total' => $total,
            'description' => $description,
            'tone' => $total > 0 ? 'warning' : 'success',
        ];
    }

    protected function currentUser(): ?User
    {
        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    /**
     * @return array<int, int>
     */
    protected function operatorBranchIds(): array
    {
        $authUser = $this->currentUser();

        if (! $authUser instanceof User) {
            return [];
        }

        return BranchAccess::accessibleBranchIds($authUser, true);
    }

    protected function applyBranchScope(Builder $query, array $branchIds, string $column = 'branch_id'): Builder
    {
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    protected function latestOperationalKpiSnapshotDate(array $branchIds): string
    {
        return $this->operationalKpiSnapshotReadModelService->latestSnapshotDate($branchIds);
    }

    protected function formatMoney(mixed $amount): string
    {
        return number_format((float) $amount, 0, ',', '.').'đ';
    }

    /**
     * @param  Collection<int, array{severity:string, code:string, message:string}>  $issues
     */
    protected function toneFromIssues(Collection $issues): string
    {
        if ($issues->contains(fn (array $issue): bool => ($issue['severity'] ?? null) === 'error')) {
            return 'danger';
        }

        if ($issues->contains(fn (array $issue): bool => ($issue['severity'] ?? null) === 'warning')) {
            return 'warning';
        }

        return 'success';
    }

    protected function toneLabel(string $tone): string
    {
        return match ($tone) {
            'success' => 'Healthy',
            'warning' => 'Needs attention',
            'danger' => 'Unhealthy',
            default => 'Info',
        };
    }

    /**
     * @param  array<string, mixed>  $runtimeBackup
     * @param  array<int, array<string, mixed>>  $backupFixtures
     * @return array<string, mixed>
     */
    protected function buildBackupOverviewCard(array $runtimeBackup, array $backupFixtures): array
    {
        $healthyFixtures = collect($backupFixtures)->where('tone', 'success')->count();

        return [
            'title' => 'Backup & restore',
            'value' => (string) ($runtimeBackup['status'] ?? 'Unknown'),
            'description' => 'Runtime path và QA fixtures cho backup health / restore drill.',
            'tone' => (string) ($runtimeBackup['tone'] ?? 'warning'),
            'status' => 'Fixtures ok: '.$healthyFixtures.'/'.count($backupFixtures),
            'meta' => [
                'Runtime path '.((string) ($runtimeBackup['path'] ?? '-')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $integrations
     * @return array<string, mixed>
     */
    protected function buildIntegrationOverviewCard(array $integrations): array
    {
        return [
            'title' => 'Integrations',
            'value' => (string) ($integrations['status'] ?? 'Unknown'),
            'description' => 'Provider readiness, secret rotation grace state và retention backlog cho integration logs.',
            'tone' => (string) ($integrations['tone'] ?? 'warning'),
            'status' => 'Expired '.count((array) ($integrations['expired_grace_rotations'] ?? [])),
            'meta' => [
                'Provider drift '.((int) data_get($integrations, 'provider_counts.degraded', 0)),
                'Prune backlog '.collect((array) ($integrations['retention_candidates'] ?? []))->sum('total'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $kpi
     * @return array<string, mixed>
     */
    protected function buildKpiOverviewCard(array $kpi): array
    {
        $snapshotCounts = (array) ($kpi['snapshot_counts'] ?? []);

        return [
            'title' => 'KPI freshness',
            'value' => (string) ($kpi['status'] ?? 'Unknown'),
            'description' => 'Snapshot SLA, hot aggregate readiness và alert ownership theo branch scope hiện tại.',
            'tone' => (string) ($kpi['tone'] ?? 'warning'),
            'status' => 'Missing '.((int) ($snapshotCounts['missing'] ?? 0)).' / Stale '.((int) ($snapshotCounts['stale'] ?? 0)),
            'meta' => [
                'Open alerts '.count((array) ($kpi['open_alerts'] ?? [])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $finance
     * @return array<string, mixed>
     */
    protected function buildFinanceOverviewCard(array $finance): array
    {
        return [
            'title' => 'Finance & collections',
            'value' => (string) ($finance['status'] ?? 'Unknown'),
            'description' => 'Invoice aging, reversal watchlist và installment dunning theo branch scope hiện tại.',
            'tone' => (string) ($finance['tone'] ?? 'warning'),
            'status' => 'Signals '.collect((array) ($finance['signals'] ?? []))->sum(function (array $signal): int {
                $value = $signal['value'] ?? 0;

                return is_numeric($value) ? (int) $value : 0;
            }),
            'meta' => [
                'Visible branches '.count($this->operatorBranchIds()),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $governance
     * @return array<string, mixed>
     */
    protected function buildGovernanceOverviewCard(array $governance): array
    {
        return [
            'title' => 'Governance & audit',
            'value' => (string) ($governance['status'] ?? 'Unknown'),
            'description' => 'Role matrix baseline, branch-scoped visibility và audit review boundary trên OPS cockpit.',
            'tone' => (string) ($governance['tone'] ?? 'info'),
            'status' => (string) ($governance['policy_note'] ?? 'Governance overview'),
            'meta' => [
                'Scenario users '.count((array) ($governance['scenario_users'] ?? [])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $zns
     * @return array<string, mixed>
     */
    protected function buildZnsOverviewCard(array $zns): array
    {
        return [
            'title' => 'ZNS cockpit',
            'value' => (string) ($zns['status'] ?? 'Unknown'),
            'description' => 'Retry/dead-letter backlog, failed campaigns và retention backlog ZNS.',
            'tone' => (string) ($zns['tone'] ?? 'warning'),
            'status' => 'Dead '.collect((array) ($zns['summary_cards'] ?? []))
                ->whereIn('label', ['Automation dead-letter', 'Delivery terminal failed', 'Campaign failed'])
                ->sum('value'),
            'meta' => [
                'Prune backlog '.collect((array) ($zns['retention_candidates'] ?? []))->sum('total'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $latestRuntimeReport
     * @param  array<string, mixed>|null  $latestRuntimeSignoff
     * @param  array<int, array<string, mixed>>  $readinessFixtures
     * @param  array<int, array<string, mixed>>  $signoffFixtures
     * @return array<string, mixed>
     */
    protected function buildReadinessOverviewCard(
        ?array $latestRuntimeReport,
        ?array $latestRuntimeSignoff,
        array $readinessFixtures,
        array $signoffFixtures,
    ): array {
        $referenceArtifact = $latestRuntimeSignoff
            ?? $latestRuntimeReport
            ?? collect($signoffFixtures)->first()
            ?? collect($readinessFixtures)->first();

        return [
            'title' => 'Readiness artifacts',
            'value' => (string) ($referenceArtifact['status'] ?? 'UNKNOWN'),
            'description' => 'Report/signoff phục vụ release gate và local QA sign-off.',
            'tone' => (string) ($referenceArtifact['tone'] ?? 'warning'),
            'status' => $referenceArtifact !== null ? (string) ($referenceArtifact['label'] ?? 'Fixture') : 'No artifact',
            'meta' => [
                $referenceArtifact !== null
                    ? ((string) ($referenceArtifact['path'] ?? '-'))
                    : 'Chưa có artifact runtime nào được tạo.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $observability
     * @return array<string, mixed>
     */
    protected function buildObservabilityOverviewCard(array $observability): array
    {
        return [
            'title' => 'Observability',
            'value' => (string) ($observability['status'] ?? 'Unknown'),
            'description' => 'Dead-letter, KPI alert, snapshot SLA và failure budget trong cửa sổ lookback.',
            'tone' => (string) ($observability['tone'] ?? 'warning'),
            'status' => 'Window '.((string) ($observability['window_hours'] ?? 0)).'h',
            'meta' => [
                'Breaches '.count((array) ($observability['breaches'] ?? [])),
            ],
        ];
    }

    /**
     * @param  list<string>  $validationErrors
     */
    protected function backupErrorCode(
        bool $directoryExists,
        ?string $manifestPath,
        array $validationErrors,
        bool $artifactExists,
        bool $sizeValid,
        bool $checksumValid,
    ): string {
        if (! $directoryExists) {
            return 'backup_directory_missing';
        }

        if ($manifestPath === null) {
            return 'backup_manifest_missing';
        }

        if ($validationErrors !== []) {
            return $validationErrors[0];
        }

        if (! $artifactExists) {
            return 'backup_artifact_missing';
        }

        if (! $sizeValid) {
            return 'backup_artifact_size_invalid';
        }

        if (! $checksumValid) {
            return 'backup_artifact_checksum_mismatch';
        }

        return 'backup_unhealthy';
    }

    protected function observabilityLabel(string $metric): string
    {
        return match ($metric) {
            'dead_backlog_total' => 'Dead-letter backlog',
            'retryable_failed_backlog_total' => 'Retryable failed backlog',
            'open_kpi_alerts' => 'Open KPI alerts',
            'snapshot_sla_violations' => 'Snapshot SLA violations',
            'recent_automation_failures' => 'Recent automation failures',
            'missing_runbook_categories' => 'Missing runbook categories',
            default => $metric,
        };
    }

    protected function decodeJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function formatDateTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i');
        }

        return (string) $value;
    }
}
