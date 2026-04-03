<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\OpsControlCenterService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

class OpsControlCenter extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Trung tâm OPS';

    protected static string|UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ops-control-center';

    protected string $view = 'filament.pages.ops-control-center';

    public array $state = [];

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->hasRole('Admin')
            && $authUser->can('View:OpsControlCenter');
    }

    public function mount(OpsControlCenterService $service): void
    {
        $this->state = $service->build();
    }

    public function getHeading(): string
    {
        return 'Trung tâm OPS';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Theo dõi control-plane local/test cho backup, restore, readiness và observability sau mỗi lần reset seed.';
    }

    /**
     * @return array{
     *     overview_panel:array{
     *         cards:array<int, array<string, mixed>>
     *     },
     *     detail_columns:array<int, array{
     *         column_classes:string,
     *         sections:array<int, array{
     *             heading:string,
     *             description:string,
     *             partial:string,
     *             include_data:array<string, mixed>
     *         }>
     *     }>
     * }
     */
    #[Computed]
    public function dashboardViewState(): array
    {
        return [
            'overview_panel' => [
                'cards' => $this->renderedOverviewCards(),
            ],
            'detail_columns' => [
                [
                    'column_classes' => 'ops-column space-y-6',
                    'sections' => $this->primaryColumnSections(),
                ],
                [
                    'column_classes' => 'ops-column space-y-6',
                    'sections' => $this->secondaryColumnSections(),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     title:string,
     *     value:string,
     *     description:string,
     *     status:string,
     *     tone:string,
     *     meta:array<int, string>,
     *     status_badge_classes:string
     * }>
     */
    protected function renderedOverviewCards(): array
    {
        return array_map(function (array $card): array {
            $tone = (string) ($card['tone'] ?? 'info');

            return [
                'title' => (string) ($card['title'] ?? ''),
                'value' => (string) ($card['value'] ?? ''),
                'description' => (string) ($card['description'] ?? ''),
                'status' => (string) ($card['status'] ?? ''),
                'tone' => $tone,
                'meta' => array_map(
                    static fn (mixed $meta): string => (string) $meta,
                    (array) ($card['meta'] ?? []),
                ),
                'status_badge_classes' => $this->toneBadgeClass($tone),
            ];
        }, (array) ($this->state['overview_cards'] ?? []));
    }

    /**
     * @return array<int, array{
     *     heading:string,
     *     description:string,
     *     partial:string,
     *     include_data:array<string, mixed>
     * }>
     */
    protected function primaryColumnSections(): array
    {
        return [
            [
                'heading' => 'Automation actor',
                'description' => 'Kiểm tra service account dùng cho scheduler và command automation.',
                'partial' => 'filament.pages.partials.ops-automation-actor-panel',
                'include_data' => ['panel' => $this->renderedAutomationActorPanel()],
            ],
            [
                'heading' => 'Backup & restore',
                'description' => 'Runtime path thật và fixture fail/pass để QA chạy smoke local.',
                'partial' => 'filament.pages.partials.ops-backup-restore-panel',
                'include_data' => [
                    'runtimeBackupPanel' => $this->renderedRuntimeBackupPanel(),
                    'backupFixturesPanel' => $this->renderedBackupFixturesPanel(),
                ],
            ],
            [
                'heading' => 'Readiness artifacts',
                'description' => 'Theo dõi artifact report/signoff local và runtime để release gate không còn nằm trong CLI.',
                'partial' => 'filament.pages.partials.ops-readiness-artifacts-panel',
                'include_data' => ['panel' => $this->renderedReadinessArtifactsPanel()],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     heading:string,
     *     description:string,
     *     partial:string,
     *     include_data:array<string, mixed>
     * }>
     */
    protected function secondaryColumnSections(): array
    {
        return [
            [
                'heading' => 'Integrations & secret rotation',
                'description' => 'Nhìn nhanh grace token, retention backlog và chuyển tiếp sang trang integration settings.',
                'partial' => 'filament.pages.partials.ops-integrations-panel',
                'include_data' => ['panel' => $this->renderedIntegrationsPanel()],
            ],
            [
                'heading' => 'KPI freshness & alerts',
                'description' => 'Tóm tắt snapshot SLA, hot aggregate readiness và owner của alert đang mở theo branch scope hiện tại.',
                'partial' => 'filament.pages.partials.ops-kpi-panel',
                'include_data' => ['panel' => $this->renderedKpiPanel()],
            ],
            [
                'heading' => 'Finance & collections',
                'description' => 'Nhìn nhanh aging sync, dunning và receipt reversal watchlist theo branch scope hiện tại.',
                'partial' => 'filament.pages.partials.ops-finance-panel',
                'include_data' => ['panel' => $this->renderedFinancePanel()],
            ],
            [
                'heading' => 'ZNS triage cockpit',
                'description' => 'Tóm tắt backlog retry/dead-letter, retention và lối tắt sang campaign workflow.',
                'partial' => 'filament.pages.partials.ops-zns-panel',
                'include_data' => ['panel' => $this->renderedZnsPanel()],
            ],
            [
                'heading' => 'Observability',
                'description' => 'Error budget theo runtime settings và metric hiện tại sau khi seed.',
                'partial' => 'filament.pages.partials.ops-observability-panel',
                'include_data' => ['panel' => $this->renderedObservabilityPanel()],
            ],
            [
                'heading' => 'Governance & audit scope',
                'description' => 'Kiểm tra role matrix baseline và những gì manager/admin thấy được trong user directory và audit trail.',
                'partial' => 'filament.pages.partials.ops-governance-panel',
                'include_data' => ['panel' => $this->renderedGovernancePanel()],
            ],
            [
                'heading' => 'Smoke pack',
                'description' => 'Bộ lệnh local/test chuẩn để QA reset seed rồi replay control-plane, integration maintenance và ZNS triage.',
                'partial' => 'filament.pages.partials.ops-command-list',
                'include_data' => ['commands' => $this->renderedSmokePackPanel()['commands']],
            ],
            [
                'heading' => 'Recent operator runs',
                'description' => 'Nhật ký command gần nhất cho OPS, integration maintenance và ZNS để operator xem nhanh status mà không cần mở audit resource.',
                'partial' => 'filament.pages.partials.ops-recent-runs-table',
                'include_data' => ['panel' => $this->renderedRecentRunsPanel()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function automationActor(): array
    {
        return (array) ($this->state['automation_actor'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function runtimeBackup(): array
    {
        return (array) ($this->state['runtime_backup'] ?? []);
    }

    /**
     * @return array{
     *     status_badge_label:string,
     *     status_badge_classes:string,
     *     label:string,
     *     meta:array<int, array{label:string, value:mixed}>,
     *     issues:array<int, array{severity:string, code:string, message:string}>
     * }
     */
    protected function renderedAutomationActorPanel(): array
    {
        $automationActor = $this->automationActor();

        return [
            'status_badge_label' => (string) ($automationActor['status'] ?? 'Info'),
            'status_badge_classes' => $this->toneBadgeClass((string) ($automationActor['tone'] ?? 'info')),
            'label' => (string) ($automationActor['label'] ?? 'Chưa cấu hình'),
            'meta' => (array) ($automationActor['meta'] ?? []),
            'issues' => (array) ($automationActor['issues'] ?? []),
        ];
    }

    /**
     * @return array{
     *     status_badge_label:string,
     *     status_badge_classes:string,
     *     label:string,
     *     description:string,
     *     meta:array<int, array{label:string, value:mixed}>,
     *     path:string,
     *     error:?string
     * }
     */
    protected function renderedRuntimeBackupPanel(): array
    {
        $runtimeBackup = $this->runtimeBackup();

        return [
            'status_badge_label' => (string) ($runtimeBackup['status'] ?? 'Unknown'),
            'status_badge_classes' => $this->toneBadgeClass((string) ($runtimeBackup['tone'] ?? 'info')),
            'label' => (string) ($runtimeBackup['label'] ?? 'Runtime backup path'),
            'description' => (string) ($runtimeBackup['description'] ?? ''),
            'meta' => (array) ($runtimeBackup['meta'] ?? []),
            'path' => (string) ($runtimeBackup['path'] ?? '-'),
            'error' => filled($runtimeBackup['error'] ?? null) ? (string) $runtimeBackup['error'] : null,
        ];
    }

    /**
     * @return array{items:array<int, array<string, mixed>>}
     */
    protected function renderedBackupFixturesPanel(): array
    {
        return [
            'items' => $this->renderedArtifactCards($this->backupFixtures()),
        ];
    }

    /**
     * @return array{
     *     runtime_artifacts:array<int, array<string, mixed>>,
     *     fixture_columns:array<int, array{
     *         items:array<int, array<string, mixed>>,
     *         error_text_classes:?string
     *     }>
     * }
     */
    protected function renderedReadinessArtifactsPanel(): array
    {
        return [
            'runtime_artifacts' => $this->renderedArtifactCards(array_values(array_filter([
                $this->latestRuntimeReport(),
                $this->latestRuntimeSignoff(),
            ]))),
            'fixture_columns' => [
                [
                    'items' => $this->renderedArtifactCards($this->readinessFixtures()),
                    'error_text_classes' => null,
                ],
                [
                    'items' => $this->renderedArtifactCards($this->signoffFixtures()),
                    'error_text_classes' => 'mt-1 font-medium text-warning-700 dark:text-warning-300',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function integrations(): array
    {
        return (array) ($this->state['integrations'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     meta:array<int, array{label:string,value:mixed}>,
     *     provider_health:array{
     *         heading:string,
     *         description:string,
     *         drift_badge_label:string,
     *         drift_badge_classes:string,
     *         items:array<int, array<string, mixed>>
     *     },
     *     active_grace:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_text:string,
     *         items:array<int, array{
     *             key:string,
     *             display_name:string,
     *             detail_text:string,
     *             card_classes:string
     *         }>
     *     },
     *     expired_grace:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_text:string,
     *         items:array<int, array{
     *             key:string,
     *             display_name:string,
     *             detail_text:string,
     *             card_classes:string
     *         }>
     *     },
     *     retention_candidates:array<int, array<string, mixed>>,
     *     links:array<int, array<string, mixed>>
     * }
     */
    protected function renderedIntegrationsPanel(): array
    {
        $integrations = $this->integrations();
        $activeGraceRotations = (array) ($integrations['active_grace_rotations'] ?? []);
        $expiredGraceRotations = (array) ($integrations['expired_grace_rotations'] ?? []);
        $providerDriftCount = (int) data_get($integrations, 'provider_counts.degraded', 0);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($integrations['status'] ?? 'Unknown'),
                description: 'Secret rotation grace state và retention candidate cho web lead, webhook, EMR, Google Calendar.',
                tone: (string) ($integrations['tone'] ?? 'info'),
                badgeLabel: count($expiredGraceRotations).' expired',
            ),
            'meta' => (array) ($integrations['meta'] ?? []),
            'provider_health' => [
                'heading' => 'Provider readiness',
                'description' => 'Contract chung cho Zalo OA, ZNS, Google Calendar và EMR.',
                'drift_badge_label' => $providerDriftCount.' drift',
                'drift_badge_classes' => $this->toneBadgeClass($providerDriftCount > 0 ? 'danger' : 'success'),
                'items' => (array) ($integrations['providers'] ?? []),
            ],
            'active_grace' => [
                'heading' => 'Active grace tokens',
                'badge_label' => (string) count($activeGraceRotations),
                'badge_classes' => $this->toneBadgeClass($activeGraceRotations === [] ? 'success' : 'warning'),
                'empty_state_text' => 'Không có grace token nào còn hiệu lực.',
                'items' => $this->renderedGraceRotationItems($activeGraceRotations, expired: false),
            ],
            'expired_grace' => [
                'heading' => 'Expired grace tokens',
                'badge_label' => (string) count($expiredGraceRotations),
                'badge_classes' => $this->toneBadgeClass($expiredGraceRotations === [] ? 'success' : 'danger'),
                'empty_state_text' => 'Không có grace token nào chờ revoke.',
                'items' => $this->renderedGraceRotationItems($expiredGraceRotations, expired: true),
            ],
            'retention_candidates' => $this->renderedRetentionCandidates(
                (array) ($integrations['retention_candidates'] ?? []),
            ),
            'links' => (array) ($integrations['links'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function kpi(): array
    {
        return (array) ($this->state['kpi'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     meta:array<int, array{label:string,value:mixed}>,
     *     snapshot_count_cards:array<int, array<string, mixed>>,
     *     aggregate_readiness_cards:array<int, array<string, mixed>>,
     *     open_alert_panel:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_message:string,
     *         cards:array<int, array{
     *             title:string,
     *             meta_text:string,
     *             badge_label:string,
     *             badge_classes:string
     *         }>
     *     },
     *     links:array<int, array<string, mixed>>
     * }
     */
    protected function renderedKpiPanel(): array
    {
        $kpi = $this->kpi();
        $openAlerts = (array) ($kpi['open_alerts'] ?? []);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($kpi['status'] ?? 'Unknown'),
                description: 'Snapshot date '.((string) ($kpi['snapshot_date'] ?? '-')).' · visible branches theo branch scope hiện tại.',
                tone: (string) ($kpi['tone'] ?? 'info'),
                badgeLabel: count($openAlerts).' open',
            ),
            'meta' => (array) ($kpi['meta'] ?? []),
            'snapshot_count_cards' => (array) ($kpi['snapshot_count_cards'] ?? []),
            'aggregate_readiness_cards' => (array) ($kpi['aggregate_readiness_cards'] ?? []),
            'open_alert_panel' => [
                'heading' => 'Open alerts',
                'badge_label' => (string) count($openAlerts),
                'badge_classes' => $this->toneBadgeClass($openAlerts === [] ? 'success' : 'warning'),
                'empty_state_message' => 'Không có KPI alert mở trong branch scope hiện tại.',
                'cards' => $this->renderedKpiOpenAlertCards($openAlerts),
            ],
            'links' => (array) ($kpi['links'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function zns(): array
    {
        return (array) ($this->state['zns'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     summary_cards:array<int, array<string, mixed>>,
     *     retention_candidates:array<int, array<string, mixed>>,
     *     links:array<int, array<string, mixed>>
     * }
     */
    protected function renderedZnsPanel(): array
    {
        $zns = $this->zns();
        $summaryCards = (array) ($zns['summary_cards'] ?? []);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($zns['status'] ?? 'Unknown'),
                description: 'Backlog automation, campaign failures và retention candidate sau mỗi lần seed/reset.',
                tone: (string) ($zns['tone'] ?? 'info'),
                badgeLabel: count($summaryCards).' signals',
            ),
            'summary_cards' => $summaryCards,
            'retention_candidates' => $this->renderedRetentionCandidates(
                (array) ($zns['retention_candidates'] ?? []),
            ),
            'links' => (array) ($zns['links'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function finance(): array
    {
        return (array) ($this->state['finance'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     meta:array<int, array{label:string,value:mixed}>,
     *     signals:array<int, array{label:string,badge_label:string,badge_classes:string}>,
     *     watchlist_panel:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_message:string,
     *         cards:array<int, array{
     *             title:string,
     *             subtitle:string,
     *             detail:string,
     *             badge_label:string,
     *             badge_classes:string
     *         }>
     *     },
     *     links:array<int, array<string, mixed>>
     * }
     */
    protected function renderedFinancePanel(): array
    {
        $finance = $this->finance();
        $watchlist = (array) ($finance['watchlist'] ?? []);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($finance['status'] ?? 'Unknown'),
                description: 'Finance watchlist branch-scoped cho overdue sync, collections và receipt reversal sau mỗi lần reset seed.',
                tone: (string) ($finance['tone'] ?? 'info'),
                badgeLabel: count($watchlist).' watch item',
            ),
            'meta' => (array) ($finance['meta'] ?? []),
            'signals' => $this->renderedSignalCards((array) ($finance['signals'] ?? [])),
            'watchlist_panel' => [
                'heading' => 'Finance watchlist',
                'badge_label' => (string) count($watchlist),
                'badge_classes' => $this->toneBadgeClass($watchlist === [] ? 'success' : 'warning'),
                'empty_state_message' => 'Không có finance watchlist nào trong branch scope hiện tại.',
                'cards' => $this->renderedFinanceWatchlistCards($watchlist),
            ],
            'links' => (array) ($finance['links'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function governance(): array
    {
        return (array) ($this->state['governance'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     meta:array<int, array{label:string,value:mixed}>,
     *     signals:array<int, array{label:string,badge_label:string,badge_classes:string}>,
     *     scenario_user_panel:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_message:string,
     *         cards:array<int, array{
     *             title:string,
     *             meta_text:string,
     *             badge_label:?string,
     *             badge_classes:string
     *         }>
     *     },
     *     recent_audit_panel:array{
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         empty_state_message:string,
     *         cards:array<int, array{
     *             title:string,
     *             meta_text:string
     *         }>
     *     },
     *     links:array<int, array<string, mixed>>
     * }
     */
    protected function renderedGovernancePanel(): array
    {
        $governance = $this->governance();
        $scenarioUsers = (array) ($governance['scenario_users'] ?? []);
        $recentAudits = (array) ($governance['recent_audits'] ?? []);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($governance['status'] ?? 'Unknown'),
                description: (string) ($governance['policy_note'] ?? ''),
                tone: (string) ($governance['tone'] ?? 'info'),
                badgeLabel: count($scenarioUsers).' scenario user',
            ),
            'meta' => (array) ($governance['meta'] ?? []),
            'signals' => $this->renderedSignalCards((array) ($governance['signals'] ?? [])),
            'scenario_user_panel' => [
                'heading' => 'Scenario users',
                'badge_label' => (string) count($scenarioUsers),
                'badge_classes' => $this->toneBadgeClass($scenarioUsers === [] ? 'warning' : 'success'),
                'empty_state_message' => 'Không có governance scenario user nào hiển thị trong scope hiện tại.',
                'cards' => $this->renderedGovernanceScenarioUserCards($scenarioUsers),
            ],
            'recent_audit_panel' => [
                'heading' => 'Recent audits',
                'badge_label' => (string) count($recentAudits),
                'badge_classes' => $this->toneBadgeClass($recentAudits === [] ? 'warning' : 'success'),
                'empty_state_message' => 'Không có audit entry nào được mở từ cockpit này.',
                'cards' => $this->renderedGovernanceRecentAuditCards($recentAudits),
            ],
            'links' => (array) ($governance['links'] ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function backupFixtures(): array
    {
        return (array) ($this->state['backup_fixtures'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readinessFixtures(): array
    {
        return (array) ($this->state['readiness_fixtures'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function signoffFixtures(): array
    {
        return (array) ($this->state['signoff_fixtures'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function latestRuntimeReport(): ?array
    {
        $report = $this->state['latest_runtime_report'] ?? null;

        return is_array($report) ? $report : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function latestRuntimeSignoff(): ?array
    {
        $signoff = $this->state['latest_runtime_signoff'] ?? null;

        return is_array($signoff) ? $signoff : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $artifacts
     * @return array<int, array<string, mixed>>
     */
    protected function renderedArtifactCards(array $artifacts): array
    {
        return array_map(function (array $artifact): array {
            return [
                ...$artifact,
                'status_badge_classes' => $this->toneBadgeClass((string) ($artifact['tone'] ?? 'info')),
            ];
        }, $artifacts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function observability(): array
    {
        return (array) ($this->state['observability'] ?? []);
    }

    /**
     * @return array{
     *     summary:array{title:string,description:string,badge_label:string,badge_classes:string},
     *     metric_cards:array<int, array{label:string,key:string,value_label:string,budget_label:string}>,
     *     breach_cards:array<int, array{label:string,badge_label:string,badge_classes:string}>,
     *     missing_runbook_panel:array{
     *         is_visible:bool,
     *         heading:string,
     *         badge_label:string,
     *         badge_classes:string,
     *         items:array<int, string>,
     *         empty_state_message:string
     *     }
     * }
     */
    protected function renderedObservabilityPanel(): array
    {
        $observability = $this->observability();
        $breaches = (array) ($observability['breaches'] ?? []);
        $missingRunbookCategories = (array) ($observability['missing_runbook_categories'] ?? []);

        return [
            'summary' => $this->renderedSectionSummary(
                title: (string) ($observability['status'] ?? 'Unknown'),
                description: 'Window '.((int) ($observability['window_hours'] ?? 0)).'h · Snapshot '.((string) ($observability['snapshot_date'] ?? '-')),
                tone: (string) ($observability['tone'] ?? 'info'),
                badgeLabel: count($breaches).' breach',
            ),
            'metric_cards' => $this->renderedObservabilityMetricCards(
                (array) ($observability['metrics'] ?? []),
            ),
            'breach_cards' => $this->renderedObservabilityBreachCards($breaches),
            'missing_runbook_panel' => $this->renderedMissingRunbookPanel($missingRunbookCategories),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentRuns(): array
    {
        return (array) ($this->state['recent_runs'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    protected function smokeCommands(): array
    {
        return (array) ($this->state['smoke_commands'] ?? []);
    }

    /**
     * @return array{commands:array<int, string>}
     */
    protected function renderedSmokePackPanel(): array
    {
        return [
            'commands' => $this->smokeCommands(),
        ];
    }

    /**
     * @return array{
     *     is_empty:bool,
     *     empty_state_message:string,
     *     rows:array<int, array{
     *         command:string,
     *         status:string,
     *         actor:string,
     *         occurred_at:string,
     *         summary:string,
     *         status_badge_classes:string
     *     }>
     * }
     */
    protected function renderedRecentRunsPanel(): array
    {
        $rows = array_map(function (array $run): array {
            $statusText = strtolower((string) ($run['status'] ?? ''));
            $actionText = strtolower((string) ($run['action'] ?? ''));
            $tone = str_contains($statusText, 'fail') || str_contains($actionText, 'fail')
                ? 'danger'
                : 'success';

            return [
                'command' => (string) ($run['command'] ?? '-'),
                'status' => (string) ($run['status'] ?? '-'),
                'actor' => (string) ($run['actor'] ?? '-'),
                'occurred_at' => (string) ($run['occurred_at'] ?? '-'),
                'summary' => (string) ($run['summary'] ?? '-'),
                'status_badge_classes' => $this->toneBadgeClass($tone),
            ];
        }, $this->recentRuns());

        return [
            'is_empty' => $rows === [],
            'empty_state_message' => 'Chưa có lần chạy command OPS nào trong audit log. Hãy chạy smoke pack ở bên trên để page bắt đầu hiển thị history.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,description:string,badge_label:string,badge_classes:string}
     */
    protected function renderedSectionSummary(
        string $title,
        string $description,
        string $tone,
        string $badgeLabel,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'badge_label' => $badgeLabel,
            'badge_classes' => $this->toneBadgeClass($tone),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @return array<int, array{label:string,badge_label:string,badge_classes:string}>
     */
    protected function renderedSignalCards(array $signals): array
    {
        return array_map(function (array $signal): array {
            return [
                'label' => (string) ($signal['label'] ?? ''),
                'badge_label' => (string) ($signal['badge_label'] ?? $signal['value'] ?? '-'),
                'badge_classes' => (string) ($signal['badge_classes'] ?? $this->toneBadgeClass(
                    (string) ($signal['tone'] ?? 'info'),
                )),
            ];
        }, $signals);
    }

    /**
     * @param  array<int, array<string, mixed>>  $openAlerts
     * @return array<int, array{
     *     title:string,
     *     meta_text:string,
     *     badge_label:string,
     *     badge_classes:string
     * }>
     */
    protected function renderedKpiOpenAlertCards(array $openAlerts): array
    {
        return array_map(function (array $alert): array {
            $status = (string) ($alert['status'] ?? '-');

            return [
                'title' => (string) ($alert['title'] ?? '-'),
                'meta_text' => ((string) ($alert['branch'] ?? '-')).' · owner '.((string) ($alert['owner'] ?? '-')),
                'badge_label' => strtoupper($status).' · '.((string) ($alert['severity'] ?? '-')),
                'badge_classes' => $this->toneBadgeClass($status === 'new' ? 'danger' : 'warning'),
            ];
        }, $openAlerts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $watchlist
     * @return array<int, array{
     *     title:string,
     *     subtitle:string,
     *     detail:string,
     *     badge_label:string,
     *     badge_classes:string
     * }>
     */
    protected function renderedFinanceWatchlistCards(array $watchlist): array
    {
        return array_map(function (array $item): array {
            return [
                'title' => (string) ($item['title'] ?? '-'),
                'subtitle' => (string) ($item['subtitle'] ?? '-'),
                'detail' => (string) ($item['detail'] ?? '-'),
                'badge_label' => (string) ($item['badge'] ?? '-'),
                'badge_classes' => $this->toneBadgeClass((string) ($item['tone'] ?? 'info')),
            ];
        }, $watchlist);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function renderedRetentionCandidates(array $candidates): array
    {
        return array_map(function (array $candidate): array {
            return [
                ...$candidate,
                'badge_label' => (string) ($candidate['total'] ?? 0).' candidate',
                'badge_classes' => $this->toneBadgeClass((string) ($candidate['tone'] ?? 'info')),
                'detail_label' => 'Retention '.((string) ($candidate['retention_days'] ?? '-')).' ngày',
            ];
        }, $candidates);
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array<int, array{label:string,key:string,value_label:string,budget_label:string}>
     */
    protected function renderedObservabilityMetricCards(array $metrics): array
    {
        return array_map(function (array $metric): array {
            return [
                'label' => (string) ($metric['label'] ?? ''),
                'key' => (string) ($metric['key'] ?? ''),
                'value_label' => (string) ($metric['value'] ?? '-'),
                'budget_label' => 'Budget '.((string) ($metric['budget'] ?? '-')),
            ];
        }, $metrics);
    }

    /**
     * @param  array<int, array<string, mixed>>  $breaches
     * @return array<int, array{label:string,badge_label:string,badge_classes:string}>
     */
    protected function renderedObservabilityBreachCards(array $breaches): array
    {
        return array_map(function (array $breach): array {
            return [
                'label' => $this->observabilityMetricLabel((string) ($breach['metric'] ?? '')),
                'badge_label' => ((string) ($breach['value'] ?? '-')).' / '.((string) ($breach['budget'] ?? '-')),
                'badge_classes' => $this->toneBadgeClass('danger'),
            ];
        }, $breaches);
    }

    /**
     * @param  array<int, string>  $missingRunbookCategories
     * @return array{
     *     is_visible:bool,
     *     heading:string,
     *     badge_label:string,
     *     badge_classes:string,
     *     items:array<int, string>,
     *     empty_state_message:string
     * }
     */
    protected function renderedMissingRunbookPanel(array $missingRunbookCategories): array
    {
        return [
            'is_visible' => $missingRunbookCategories !== [],
            'heading' => 'Thiếu runbook map',
            'badge_label' => (string) count($missingRunbookCategories),
            'badge_classes' => $this->toneBadgeClass($missingRunbookCategories === [] ? 'success' : 'warning'),
            'items' => $missingRunbookCategories,
            'empty_state_message' => 'Không còn category observability nào thiếu runbook map.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenarioUsers
     * @return array<int, array{
     *     title:string,
     *     meta_text:string,
     *     badge_label:?string,
     *     badge_classes:string
     * }>
     */
    protected function renderedGovernanceScenarioUserCards(array $scenarioUsers): array
    {
        return array_map(function (array $user): array {
            $assignments = filled($user['assignments'] ?? null) ? (string) $user['assignments'] : null;

            return [
                'title' => (string) ($user['email'] ?? '-'),
                'meta_text' => ((string) ($user['role'] ?? 'No role')).' · '.((string) ($user['branch'] ?? '-')),
                'badge_label' => $assignments,
                'badge_classes' => $this->toneBadgeClass('warning'),
            ];
        }, $scenarioUsers);
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentAudits
     * @return array<int, array{title:string,meta_text:string}>
     */
    protected function renderedGovernanceRecentAuditCards(array $recentAudits): array
    {
        return array_map(function (array $audit): array {
            return [
                'title' => strtoupper((string) ($audit['entity'] ?? '-')).' · '.((string) ($audit['action'] ?? '-')),
                'meta_text' => ((string) ($audit['actor'] ?? 'system')).' · '.((string) ($audit['occurred_at'] ?? '-')),
            ];
        }, $recentAudits);
    }

    protected function observabilityMetricLabel(string $metric): string
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

    /**
     * @param  array<int, array<string, mixed>>  $rotations
     * @return array<int, array{
     *     key:string,
     *     display_name:string,
     *     detail_text:string,
     *     card_classes:string
     * }>
     */
    protected function renderedGraceRotationItems(array $rotations, bool $expired): array
    {
        return array_map(function (array $rotation) use ($expired): array {
            $displayName = (string) ($rotation['display_name'] ?? '');
            $detailText = $expired
                ? 'Hết hạn '.((string) ($rotation['grace_expires_at_label'] ?? '-')).' · '.((string) ($rotation['expired_minutes_label'] ?? '-'))
                : 'Grace tới '.((string) ($rotation['grace_expires_at_label'] ?? '-')).' · '.((string) ($rotation['remaining_minutes_label'] ?? '-'));

            return [
                'key' => (string) ($rotation['key'] ?? $displayName),
                'display_name' => $displayName,
                'detail_text' => $detailText,
                'card_classes' => $expired
                    ? 'rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100'
                    : 'rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900/60',
            ];
        }, $rotations);
    }

    protected function toneBadgeClass(?string $tone): string
    {
        return match ($tone) {
            'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
            'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
            'danger' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
            'info' => 'border-info-200 bg-info-50 text-info-700 dark:border-info-900/60 dark:bg-info-950/30 dark:text-info-200',
            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }
}
