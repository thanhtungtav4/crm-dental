<?php

use App\Filament\Pages\OpsControlCenter;
use App\Models\User;
use App\Services\OpsControlCenterService;
use Database\Seeders\FinanceScenarioSeeder;
use Database\Seeders\GovernanceScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\File;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('allows only admin personas to access the ops control center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $response = $this->actingAs($user)
        ->get(OpsControlCenter::getUrl());

    $response
        ->assertOk()
        ->assertSee('Trung tâm OPS')
        ->assertSee('Scheduler automation actor')
        ->assertSee('Integrations & secret rotation')
        ->assertSee('KPI freshness & alerts')
        ->assertSee('Finance & collections')
        ->assertSee('Governance & audit scope')
        ->assertSee('ZNS triage cockpit')
        ->assertSee('Readiness signoff fixture');

    expect(substr_count($response->getContent(), 'Theo dõi control-plane local/test cho backup, restore, readiness và observability sau mỗi lần reset seed.'))->toBe(1)
        ->and($response->getContent())->toContain('ops-page-shell')
        ->and($response->getContent())->toContain('ops-overview-grid')
        ->and($response->getContent())->toContain('ops-detail-grid')
        ->and($response->getContent())->not->toContain('grid gap-4 md:grid-cols-2 xl:grid-cols-4')
        ->and($response->getContent())->not->toContain('grid gap-6 xl:grid-cols-[1.15fr_0.85fr]');
});

it('blocks manager, doctor, and cskh personas from accessing the ops control center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(OpsControlCenter::getUrl())
        ->assertForbidden();
})->with([
    'manager' => 'manager.q1@demo.ident.test',
    'doctor' => 'doctor.q1@demo.ident.test',
    'cskh' => 'cskh.q1@demo.ident.test',
]);

it('renders local ops fixtures, seeded signoff, and smoke commands', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('automation.bot@demo.ident.test')
        ->assertSee('Backup pass fixture')
        ->assertSee('Readiness pass fixture')
        ->assertSee('manager.q1@demo.ident.test')
        ->assertSee('REL-DEMO-OPS-001')
        ->assertSee('ops:check-backup-health')
        ->assertSee('ops:verify-production-readiness-report')
        ->assertSee('emr:reconcile-integrity')
        ->assertSee('emr:reconcile-clinical-media')
        ->assertSee('emr:check-dicom-readiness')
        ->assertSee('emr:prune-clinical-media')
        ->assertSee('google-calendar:sync-events')
        ->assertSee('integrations:revoke-rotated-secrets')
        ->assertSee('reports:check-snapshot-sla')
        ->assertSee('zns:sync-automation-events');
});

it('renders integration, kpi, and zns triage summaries from the local seed pack', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Expired grace tokens')
        ->assertSee('Provider readiness')
        ->assertSee('Zalo OA')
        ->assertSee('Google Calendar')
        ->assertSee('EMR')
        ->assertSee('DICOM / PACS')
        ->assertSee('Web Lead API')
        ->assertSee('Web Lead API Token')
        ->assertSee('Quá hạn')
        ->assertSee('Lead mail retryable')
        ->assertSee('Lead mail dead')
        ->assertSee('Popup announcement logs')
        ->assertSee('Patient photos')
        ->assertSee('Clinical media temporary')
        ->assertSee('Clinical media operational')
        ->assertSee('Delivery mail web lead')
        ->assertSee('Revenue aggregate')
        ->assertSee('ZNS automation events')
        ->assertSee('Automation dead-letter')
        ->assertSee('Danh sách campaign')
        ->assertSee('Runtime disabled')
        ->assertSee('issue');
});

it('renders ops provider readiness and zns summaries from shared presentation partials', function (): void {
    $blade = File::get(resource_path('views/filament/pages/ops-control-center.blade.php'));
    $opsOverviewCardPartial = File::get(resource_path('views/filament/pages/partials/ops-overview-card.blade.php'));
    $signalCardPartial = File::get(resource_path('views/filament/pages/partials/signal-badge-card.blade.php'));
    $retentionCandidatePartial = File::get(resource_path('views/filament/pages/partials/retention-candidate-card.blade.php'));
    $providerHealthPanelPartial = File::get(resource_path('views/filament/pages/partials/provider-health-panel.blade.php'));
    $graceRotationPanelPartial = File::get(resource_path('views/filament/pages/partials/grace-rotation-panel.blade.php'));
    $automationActorPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-automation-actor-panel.blade.php'));
    $backupRestorePanelPartial = File::get(resource_path('views/filament/pages/partials/ops-backup-restore-panel.blade.php'));
    $readinessArtifactsPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-readiness-artifacts-panel.blade.php'));
    $runtimeBackupPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-runtime-backup-panel.blade.php'));
    $artifactListPartial = File::get(resource_path('views/filament/pages/partials/ops-artifact-list.blade.php'));
    $commandListPartial = File::get(resource_path('views/filament/pages/partials/ops-command-list.blade.php'));
    $recentRunsTablePartial = File::get(resource_path('views/filament/pages/partials/ops-recent-runs-table.blade.php'));
    $observabilityPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-observability-panel.blade.php'));
    $governancePanelPartial = File::get(resource_path('views/filament/pages/partials/ops-governance-panel.blade.php'));
    $kpiPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-kpi-panel.blade.php'));
    $financePanelPartial = File::get(resource_path('views/filament/pages/partials/ops-finance-panel.blade.php'));
    $integrationsPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-integrations-panel.blade.php'));
    $znsPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-zns-panel.blade.php'));
    $controlPlaneSectionPartial = File::get(resource_path('views/filament/pages/partials/control-plane-section.blade.php'));
    $opsControlCenterShellPartial = File::get(resource_path('views/filament/pages/partials/ops-control-center-shell.blade.php'));
    $opsOverviewGridPanelPartial = File::get(resource_path('views/filament/pages/partials/ops-overview-grid-panel.blade.php'));
    $controlPlaneSectionColumnPartial = File::get(resource_path('views/filament/pages/partials/control-plane-section-column.blade.php'));
    $controlPlaneSectionListPartial = File::get(resource_path('views/filament/pages/partials/control-plane-section-list.blade.php'));

    expect($blade)
        ->not->toContain('@php($viewState = $this->dashboardViewState)')
        ->toContain("@include('filament.pages.partials.ops-control-center-shell', [")
        ->toContain("'viewState' => \$this->dashboardViewState,")
        ->not->toContain("@foreach(\$this->dashboardViewState['overview_cards'] as \$card)")
        ->not->toContain("@foreach(\$this->dashboardViewState['primary_sections'] as \$section)")
        ->not->toContain("@foreach(\$this->dashboardViewState['secondary_sections'] as \$section)")
        ->not->toContain("'toneBadgeClasses' => \$toneBadgeClasses")
        ->not->toContain("'defaultBadgeClasses' => \$defaultBadgeClasses")
        ->not->toContain("{{ number_format(\$card['value']) }}")
        ->not->toContain('{{ number_format($count) }}')
        ->not->toContain("{{ count(\$zns['summary_cards'] ?? []) }} signals")
        ->not->toContain("{{ \$candidate['total'] }} candidate")
        ->and($opsControlCenterShellPartial)
        ->toContain("@include('filament.pages.partials.ops-overview-grid-panel', [")
        ->toContain("'panel' => \$viewState['overview_panel'],")
        ->toContain("@foreach(\$viewState['detail_columns'] as \$column)")
        ->toContain("@include('filament.pages.partials.control-plane-section-column', [")
        ->and($opsOverviewGridPanelPartial)
        ->toContain("@foreach(\$panel['cards'] as \$card)")
        ->toContain("@include('filament.pages.partials.ops-overview-card', ['card' => \$card])")
        ->and($controlPlaneSectionColumnPartial)
        ->toContain("<div class=\"{{ \$column['column_classes'] }}\">")
        ->toContain("@include('filament.pages.partials.control-plane-section-list', [")
        ->toContain("'sections' => \$column['sections']")
        ->and($controlPlaneSectionListPartial)
        ->toContain('@foreach($sections as $section)')
        ->toContain("@include('filament.pages.partials.control-plane-section', ['section' => \$section])")
        ->and($controlPlaneSectionPartial)
        ->toContain(":heading=\"\$section['heading'] ?? null\"")
        ->toContain("@include(\$section['partial'], \$section['include_data'] ?? [])")
        ->and($opsOverviewCardPartial)
        ->toContain("{{ \$card['status_badge_classes'] }}")
        ->and($providerHealthPanelPartial)
        ->toContain("{{ \$panel['heading'] }}")
        ->toContain("@include('filament.pages.partials.provider-health-card'")
        ->and($graceRotationPanelPartial)
        ->toContain("{{ \$rotation['detail_text'] }}")
        ->toContain("{{ \$panel['empty_state_text'] }}")
        ->and($automationActorPanelPartial)
        ->toContain("{{ \$panel['status_badge_label'] }}")
        ->toContain("{{ strtoupper(\$issue['severity']) }}")
        ->and($backupRestorePanelPartial)
        ->toContain("@include('filament.pages.partials.ops-runtime-backup-panel', [")
        ->toContain("@include('filament.pages.partials.ops-artifact-list', [")
        ->and($readinessArtifactsPanelPartial)
        ->toContain("@foreach(\$panel['fixture_columns'] as \$column)")
        ->toContain("@include('filament.pages.partials.ops-artifact-list', [")
        ->and($runtimeBackupPanelPartial)
        ->toContain("{{ \$panel['path'] }}")
        ->and($artifactListPartial)
        ->toContain("@include('filament.pages.partials.ops-artifact-card', [")
        ->and(File::get(resource_path('views/filament/pages/partials/ops-artifact-card.blade.php')))
        ->toContain("{{ \$artifact['status_badge_classes'] }}")
        ->not->toContain('$toneBadgeClasses = [')
        ->and($commandListPartial)
        ->toContain('{{ $command }}')
        ->and($recentRunsTablePartial)
        ->toContain("{{ \$run['status_badge_classes'] }}")
        ->toContain("'message' => \$panel['empty_state_message']");

    expect($signalCardPartial)->toContain("{{ \$card['badge_label'] }}")
        ->and($retentionCandidatePartial)->toContain("{{ \$candidate['badge_label'] }}")
        ->and($observabilityPanelPartial)
        ->toContain("{{ \$metric['budget_label'] }}")
        ->toContain('Error budget breaches')
        ->toContain("{{ \$panel['missing_runbook_panel']['heading'] }}")
        ->toContain("@include('filament.pages.partials.ops-empty-state', [")
        ->and($governancePanelPartial)
        ->toContain("@include('filament.pages.partials.signal-badge-card', [")
        ->toContain("{{ \$panel['scenario_user_panel']['heading'] }}")
        ->toContain("{{ \$panel['recent_audit_panel']['heading'] }}")
        ->toContain("{{ \$card['meta_text'] }}")
        ->toContain("@include('filament.pages.partials.ops-empty-state', [")
        ->and($kpiPanelPartial)
        ->toContain("@include('filament.pages.partials.signal-badge-card', [")
        ->toContain("{{ \$panel['open_alert_panel']['heading'] }}")
        ->toContain("{{ \$card['badge_label'] }}")
        ->toContain("@include('filament.pages.partials.ops-empty-state', [")
        ->and($financePanelPartial)
        ->toContain("@include('filament.pages.partials.signal-badge-card', [")
        ->toContain("{{ \$panel['watchlist_panel']['heading'] }}")
        ->toContain("@include('filament.pages.partials.ops-empty-state', [")
        ->toContain("{{ \$card['detail'] }}")
        ->and($integrationsPanelPartial)
        ->toContain("@include('filament.pages.partials.provider-health-panel', [")
        ->toContain("@include('filament.pages.partials.grace-rotation-panel', [")
        ->toContain("@include('filament.pages.partials.retention-candidate-card', [")
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->toContain("@include('filament.pages.partials.ops-meta-grid', [")
        ->and($znsPanelPartial)
        ->toContain("@include('filament.pages.partials.dashboard-summary-card', [")
        ->toContain("@include('filament.pages.partials.retention-candidate-card', [")
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->and($kpiPanelPartial)
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->toContain("@include('filament.pages.partials.ops-meta-grid', [")
        ->and($financePanelPartial)
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->toContain("@include('filament.pages.partials.ops-meta-grid', [")
        ->and($observabilityPanelPartial)
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->and($governancePanelPartial)
        ->toContain("@include('filament.pages.partials.section-summary-banner', [")
        ->toContain("@include('filament.pages.partials.ops-meta-grid', [");
});

it('builds ops rendered panels from shared state contracts', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($admin);

    $page = new class extends OpsControlCenter
    {
        public function forceState(array $state): void
        {
            $this->state = $state;
        }
    };

    $page->forceState(app(OpsControlCenterService::class)->build());
    $opsState = $page->state;
    $integrationsState = (array) ($opsState['integrations'] ?? []);
    $automationActorState = (array) ($opsState['automation_actor'] ?? []);
    $runtimeBackupState = (array) ($opsState['runtime_backup'] ?? []);
    $backupFixturesState = (array) ($opsState['backup_fixtures'] ?? []);
    $kpiState = (array) ($opsState['kpi'] ?? []);
    $financeState = (array) ($opsState['finance'] ?? []);
    $znsState = (array) ($opsState['zns'] ?? []);
    $observabilityState = (array) ($opsState['observability'] ?? []);
    $governanceState = (array) ($opsState['governance'] ?? []);
    $smokeCommandsState = (array) ($opsState['smoke_commands'] ?? []);
    $viewState = $page->dashboardViewState();
    $overviewPanel = $viewState['overview_panel'];
    $detailColumns = $viewState['detail_columns'];
    $primarySections = collect($detailColumns[0]['sections'])->keyBy('partial');
    $secondarySections = collect($detailColumns[1]['sections'])->keyBy('partial');
    $automationActorPanel = $primarySections['filament.pages.partials.ops-automation-actor-panel']['include_data']['panel'];
    $backupRestorePanel = $primarySections['filament.pages.partials.ops-backup-restore-panel']['include_data'];
    $runtimeBackupPanel = $backupRestorePanel['runtimeBackupPanel'];
    $backupFixturesPanel = $backupRestorePanel['backupFixturesPanel'];
    $readinessArtifactsPanel = $primarySections['filament.pages.partials.ops-readiness-artifacts-panel']['include_data']['panel'];
    $integrationsPanel = $secondarySections['filament.pages.partials.ops-integrations-panel']['include_data']['panel'];
    $kpiPanel = $secondarySections['filament.pages.partials.ops-kpi-panel']['include_data']['panel'];
    $financePanel = $secondarySections['filament.pages.partials.ops-finance-panel']['include_data']['panel'];
    $znsPanel = $secondarySections['filament.pages.partials.ops-zns-panel']['include_data']['panel'];
    $observabilityPanel = $secondarySections['filament.pages.partials.ops-observability-panel']['include_data']['panel'];
    $governancePanel = $secondarySections['filament.pages.partials.ops-governance-panel']['include_data']['panel'];
    $smokeCommands = $secondarySections['filament.pages.partials.ops-command-list']['include_data']['commands'];
    $recentRunsPanel = $secondarySections['filament.pages.partials.ops-recent-runs-table']['include_data']['panel'];
    $activeGraceItems = array_values($integrationsPanel['active_grace']['items']);

    expect($integrationsPanel['summary'])->toMatchArray([
        'title' => $integrationsState['status'],
        'badge_label' => count($integrationsState['expired_grace_rotations'] ?? []).' expired',
    ])
        ->and($automationActorPanel['status_badge_label'])->toBe($automationActorState['status'])
        ->and($runtimeBackupPanel['path'])->toBe((string) ($runtimeBackupState['path'] ?? '-'))
        ->and($backupFixturesPanel['items'])->toHaveCount(count($backupFixturesState))
        ->and($backupFixturesPanel['items'] === [] || collect($backupFixturesPanel['items'][0])->has(['label', 'status', 'status_badge_classes']))->toBeTrue()
        ->and($viewState)->toHaveKeys([
            'overview_panel',
            'detail_columns',
        ])
        ->and($overviewPanel)->toHaveKeys(['cards'])
        ->and($overviewPanel['cards'])->each->toHaveKey('status_badge_classes')
        ->and($detailColumns)->toHaveCount(2)
        ->and($detailColumns[0])->toHaveKeys(['column_classes', 'sections'])
        ->and($detailColumns[1])->toHaveKeys(['column_classes', 'sections'])
        ->and($detailColumns[0]['column_classes'])->toBe('ops-column space-y-6')
        ->and($detailColumns[1]['column_classes'])->toBe('ops-column space-y-6')
        ->and($detailColumns[0]['sections'])->toHaveCount(3)
        ->and($readinessArtifactsPanel)->toHaveKeys([
            'runtime_artifacts',
            'fixture_columns',
        ])
        ->and($detailColumns[1]['sections'])->toHaveCount(8)
        ->and($readinessArtifactsPanel['fixture_columns'])->toHaveCount(2)
        ->and($readinessArtifactsPanel['runtime_artifacts'] === [] || collect($readinessArtifactsPanel['runtime_artifacts'][0])->has(['label', 'status', 'status_badge_classes']))->toBeTrue()
        ->and($readinessArtifactsPanel['fixture_columns'][1]['error_text_classes'])->toBe('mt-1 font-medium text-warning-700 dark:text-warning-300')
        ->and($readinessArtifactsPanel['fixture_columns'][0]['items'] === [] || collect($readinessArtifactsPanel['fixture_columns'][0]['items'][0])->has(['label', 'status', 'status_badge_classes']))->toBeTrue()
        ->and($smokeCommands)->toBe($smokeCommandsState)
        ->and($recentRunsPanel)->toHaveKeys([
            'is_empty',
            'empty_state_message',
            'rows',
        ])
        ->and($integrationsPanel['provider_health']['items'])->toBe($integrationsState['providers'])
        ->and($integrationsPanel['active_grace'])->toHaveKeys([
            'heading',
            'badge_label',
            'badge_classes',
            'empty_state_text',
            'items',
        ])
        ->and($activeGraceItems === [] || collect($activeGraceItems[0])->has(['display_name', 'detail_text', 'card_classes']))->toBeTrue()
        ->and(
            $recentRunsPanel['rows'] === []
            || collect($recentRunsPanel['rows'])->first() === null
            || collect($recentRunsPanel['rows'])->first()->keys()->sort()->values()->all() === [
                'actor',
                'command',
                'occurred_at',
                'status',
                'status_badge_classes',
                'summary',
            ],
        )->toBeTrue()
        ->and($kpiPanel['summary'])->toMatchArray([
            'title' => $kpiState['status'],
            'badge_label' => count($kpiState['open_alerts'] ?? []).' open',
        ])
        ->and($kpiPanel['open_alert_panel']['cards'])->toHaveCount(count($kpiState['open_alerts'] ?? []))
        ->and($financePanel['summary'])->toMatchArray([
            'title' => $financeState['status'],
            'badge_label' => count($financeState['watchlist'] ?? []).' watch item',
        ])
        ->and($financePanel['signals'])->toHaveCount(count($financeState['signals'] ?? []))
        ->and($financePanel['watchlist_panel']['cards'])->toHaveCount(count($financeState['watchlist'] ?? []))
        ->and($znsPanel['summary'])->toMatchArray([
            'title' => $znsState['status'],
            'badge_label' => count($znsState['summary_cards'] ?? []).' signals',
        ])
        ->and($observabilityPanel['summary']['title'])->toBe($observabilityState['status'])
        ->and($observabilityPanel)->toHaveKeys([
            'metric_cards',
            'breach_cards',
            'missing_runbook_panel',
        ])
        ->and($observabilityPanel['metric_cards'])->toHaveCount(count($observabilityState['metrics'] ?? []))
        ->and($governancePanel['summary']['description'])->toBe($governanceState['policy_note'])
        ->and($governancePanel['signals'])->toHaveCount(count($governanceState['signals'] ?? []))
        ->and($governancePanel)->toHaveKeys([
            'scenario_user_panel',
            'recent_audit_panel',
            'links',
        ])
        ->and($governancePanel['scenario_user_panel']['cards'])->toHaveCount(count($governanceState['scenario_users'] ?? []))
        ->and($governancePanel['recent_audit_panel']['cards'])->toHaveCount(count($governanceState['recent_audits'] ?? []));
});

it('renders finance watchlists for admin in the ops control center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($user)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee(FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
        ->assertSee('QA Finance Overdue')
        ->assertSee(FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
        ->assertSee(FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
        ->assertSee('QA Finance Installment');
});

it('shows governance details for admin', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee(GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)
        ->assertSee(GovernanceScenarioSeeder::HIDDEN_USER_EMAIL)
        ->assertSee('Admin scope available');
});

it('forbids manager from the ops control center governance overview', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(OpsControlCenter::getUrl())
        ->assertForbidden();
});
