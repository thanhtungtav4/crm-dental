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

it('allows admin and manager personas to access the ops control center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Trung tâm OPS')
        ->assertSee('Scheduler automation actor')
        ->assertSee('Integrations & secret rotation')
        ->assertSee('KPI freshness & alerts')
        ->assertSee('Finance & collections')
        ->assertSee('Governance & audit scope')
        ->assertSee('ZNS triage cockpit')
        ->assertSee('Readiness signoff fixture');
})->with([
    'admin' => 'admin@demo.nhakhoaanphuc.test',
    'manager' => 'manager.q1@demo.nhakhoaanphuc.test',
]);

it('blocks doctor and cskh personas from accessing the ops control center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(OpsControlCenter::getUrl())
        ->assertForbidden();
})->with([
    'doctor' => 'doctor.q1@demo.nhakhoaanphuc.test',
    'cskh' => 'cskh.q1@demo.nhakhoaanphuc.test',
]);

it('renders local ops fixtures, seeded signoff, and smoke commands', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('automation.bot@demo.nhakhoaanphuc.test')
        ->assertSee('Backup pass fixture')
        ->assertSee('Readiness pass fixture')
        ->assertSee('manager.q1@demo.nhakhoaanphuc.test')
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
        ->where('email', 'admin@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Expired grace tokens')
        ->assertSee('Web Lead API Token')
        ->assertSee('No-show vượt ngưỡng')
        ->assertSee('Revenue aggregate')
        ->assertSee('ZNS automation events')
        ->assertSee('Automation dead-letter')
        ->assertSee('Danh sách campaign');
});

it('renders finance watchlists for admin and manager branch scope', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee(FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
        ->assertSee('QA Finance Overdue')
        ->assertSee(FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
        ->assertSee(FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
        ->assertSee('QA Finance Installment');
})->with([
    'admin' => 'admin@demo.nhakhoaanphuc.test',
    'manager' => 'manager.q1@demo.nhakhoaanphuc.test',
]);

it('shows governance details for admin', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee(GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)
        ->assertSee(GovernanceScenarioSeeder::HIDDEN_USER_EMAIL)
        ->assertSee('Admin scope available');
});

it('keeps manager on the role-limited governance overview', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Role-limited overview')
        ->assertSee('Governance resource giữ admin-only theo baseline role matrix hiện tại.')
        ->assertDontSee(GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)
        ->assertDontSee(GovernanceScenarioSeeder::HIDDEN_USER_EMAIL);
});
