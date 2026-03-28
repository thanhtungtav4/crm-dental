<?php

use App\Filament\Pages\OpsControlCenter;
use App\Models\User;
use Database\Seeders\FinanceScenarioSeeder;
use Database\Seeders\GovernanceScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
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
        ->assertSee('Web Lead API Token')
        ->assertSee('Lead mail retryable')
        ->assertSee('Lead mail dead')
        ->assertSee('Popup announcement logs')
        ->assertSee('Patient photos')
        ->assertSee('Delivery mail web lead')
        ->assertSee('Revenue aggregate')
        ->assertSee('ZNS automation events')
        ->assertSee('Automation dead-letter')
        ->assertSee('Danh sách campaign');
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
