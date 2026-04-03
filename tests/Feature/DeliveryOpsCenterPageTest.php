<?php

use App\Filament\Pages\DeliveryOpsCenter;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\File;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('allows admin manager and doctor personas to access the delivery ops center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Điều phối điều trị')
        ->assertSee('Lối tắt delivery');
})->with([
    'admin' => 'admin@demo.ident.test',
    'manager' => 'manager.q1@demo.ident.test',
    'doctor' => 'doctor.q1@demo.ident.test',
]);

it('blocks cskh persona from accessing the delivery ops center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($cskh)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertForbidden();
});

it('renders all q1 delivery scenarios for q1 manager persona', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Workflow điều trị')
        ->assertSee('Hồ sơ lâm sàng')
        ->assertSee('Cảnh báo kho')
        ->assertSee('Labo & gia công')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent')
        ->assertSee('Loại consent')
        ->assertSee('Rủi ro cao')
        ->assertSee('Hoàn tất ký consent và rà lại hồ sơ khám trước khi thực hiện thủ thuật.')
        ->assertSee('QA Inventory Low Stock Composite')
        ->assertSee('FO-QA-SUP-001');
});

it('shows treatment and clinical delivery sections for q1 doctor without inventory and labo leakage', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Workflow điều trị')
        ->assertSee('Hồ sơ lâm sàng')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent')
        ->assertDontSee('QA Inventory Low Stock Composite')
        ->assertDontSee('FO-QA-SUP-001');
});

it('keeps q1 delivery scenarios out of scope for a cau giay doctor', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.cg@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertDontSee('QA Treatment Workflow Plan')
        ->assertDontSee('QA Clinical Consent')
        ->assertDontSee('QA Inventory Low Stock Composite')
        ->assertDontSee('FO-QA-SUP-001');
});

it('renders the delivery ops center through shared control-center partials', function (): void {
    $pageClass = File::get(app_path('Filament/Pages/DeliveryOpsCenter.php'));
    $sharedBuilderTrait = File::get(app_path('Filament/Pages/BuildsControlCenterPageViewState.php'));
    $blade = File::get(resource_path('views/filament/pages/delivery-ops-center.blade.php'));
    $shellPartial = File::get(resource_path('views/filament/pages/partials/control-center-shell.blade.php'));
    $overviewGridPartial = File::get(resource_path('views/filament/pages/partials/control-center-overview-grid.blade.php'));
    $overviewCardPartial = File::get(resource_path('views/filament/pages/partials/control-center-overview-card.blade.php'));
    $quickLinksPartial = File::get(resource_path('views/filament/pages/partials/control-center-quick-links-panel.blade.php'));
    $sectionPanelPartial = File::get(resource_path('views/filament/pages/partials/control-center-section-panel.blade.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.pages.partials.control-center-shell', [")
        ->toContain("'viewState' => \$this->pageViewState,")
        ->not->toContain('$toneBadgeClasses')
        ->not->toContain('$defaultBadgeClasses');

    expect($pageClass)
        ->toContain('use BuildsControlCenterPageViewState;')
        ->toContain('return $this->buildControlCenterPageViewState(')
        ->not->toContain('protected function renderedOverviewCards')
        ->not->toContain('protected function renderedSections')
        ->not->toContain('protected function toneBadgeClass');

    expect($sharedBuilderTrait)
        ->toContain('trait BuildsControlCenterPageViewState')
        ->toContain('protected function buildControlCenterPageViewState(')
        ->toContain('protected function renderedControlCenterOverviewCards(): array')
        ->toContain('protected function renderedControlCenterSections(): array')
        ->toContain('protected function controlCenterToneBadgeClass(string $tone): string');

    expect($shellPartial)
        ->toContain("@include('filament.pages.partials.control-center-overview-grid', [")
        ->toContain("'panel' => \$viewState['overview_panel']")
        ->toContain("@include('filament.pages.partials.control-center-quick-links-panel', [")
        ->toContain("'panel' => \$viewState['quick_links_panel']")
        ->toContain("@foreach(\$viewState['sections_panel']['sections'] as \$section)")
        ->toContain("@include('filament.pages.partials.control-center-section-panel', [");

    expect($overviewGridPartial)
        ->toContain("@foreach(\$panel['cards'] as \$card)")
        ->toContain("@include('filament.pages.partials.control-center-overview-card', [");

    expect($overviewCardPartial)
        ->toContain("{{ \$card['status_badge_classes'] }}")
        ->toContain("{{ \$card['status'] }}");

    expect($quickLinksPartial)
        ->toContain(":heading=\"\$panel['heading']\"")
        ->toContain(":description=\"\$panel['description']\"")
        ->toContain("class=\"{{ \$panel['grid_classes'] }}\"")
        ->toContain("@foreach(\$panel['links'] as \$link)");

    expect($sectionPanelPartial)
        ->toContain(":heading=\"\$section['title']\"")
        ->toContain("@foreach(\$section['metrics'] as \$metric)")
        ->toContain("{{ \$metric['badge_classes'] }}")
        ->toContain("{{ \$section['empty_state_text'] }}")
        ->toContain("@foreach(\$section['rows'] as \$row)")
        ->toContain("{{ \$row['badge_classes'] }}");
});

it('builds delivery ops page view state from shared presentation payloads', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($manager);

    $page = app(DeliveryOpsCenter::class);
    $page->mount(app(\App\Services\DeliveryOpsCenterService::class));
    $viewState = $page->pageViewState();
    $sections = collect($viewState['sections_panel']['sections']);
    $firstMetric = $sections->flatMap(fn (array $section): array => $section['metrics'] ?? [])->first();

    expect($viewState)->toHaveKeys([
        'overview_panel',
        'quick_links_panel',
        'sections_panel',
    ])
        ->and($viewState['overview_panel']['cards'])->not->toBeEmpty()
        ->and($viewState['overview_panel']['cards'][0])->toHaveKey('status_badge_classes')
        ->and($viewState['quick_links_panel'])->toMatchArray([
            'heading' => 'Lối tắt delivery',
            'grid_classes' => 'grid gap-4 md:grid-cols-2 xl:grid-cols-6',
        ])
        ->and($viewState['sections_panel']['sections'])->not->toBeEmpty()
        ->and($sections->first())->toHaveKey('empty_state_text')
        ->and($firstMetric)->not->toBeNull()
        ->and($firstMetric)->toHaveKey('badge_classes');
});
