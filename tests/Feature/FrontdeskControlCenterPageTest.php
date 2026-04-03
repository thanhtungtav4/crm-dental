<?php

use App\Filament\Pages\FrontdeskControlCenter;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\File;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('allows admin, manager, and cskh personas to access the frontdesk control center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Điều phối front-office')
        ->assertSee('Lead pipeline')
        ->assertSee('Lịch hẹn cần bàn giao')
        ->assertSee('Queue CSKH')
        ->assertSee('Khách hàng')
        ->assertSee('Queue ưu tiên');
})->with([
    'admin' => 'admin@demo.ident.test',
    'manager' => 'manager.q1@demo.ident.test',
    'cskh' => 'cskh.q1@demo.ident.test',
]);

it('blocks doctor persona from accessing the frontdesk control center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertForbidden();
});

it('renders seeded lead, appointment, and care rows for the q1 frontdesk persona only within branch scope', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($cskh)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Pham Minh Chau')
        ->assertSee('QA Appointment Base')
        ->assertSee('Nguyen Thi Thu Trang')
        ->assertDontSee('Le Van Nam')
        ->assertDontSee('Vu Thi Hong Nhung');
});

it('renders the frontdesk control center through shared control-center partials', function (): void {
    $pageClass = File::get(app_path('Filament/Pages/FrontdeskControlCenter.php'));
    $blade = File::get(resource_path('views/filament/pages/frontdesk-control-center.blade.php'));
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

    expect($shellPartial)
        ->toContain("@include('filament.pages.partials.control-center-overview-grid', [")
        ->toContain("@include('filament.pages.partials.control-center-quick-links-panel', [")
        ->toContain("@include('filament.pages.partials.control-center-section-panel', [");

    expect($overviewGridPartial)
        ->toContain("@foreach(\$panel['cards'] as \$card)");

    expect($overviewCardPartial)
        ->toContain("{{ \$card['status_badge_classes'] }}");

    expect($quickLinksPartial)
        ->toContain("class=\"{{ \$panel['grid_classes'] }}\"")
        ->toContain("@foreach(\$panel['links'] as \$link)");

    expect($sectionPanelPartial)
        ->toContain("@foreach(\$section['metrics'] as \$metric)")
        ->toContain("{{ \$metric['badge_classes'] }}")
        ->toContain("@foreach(\$section['rows'] as \$row)")
        ->toContain("{{ \$row['badge_classes'] }}");
});

it('builds frontdesk page view state from shared presentation payloads', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($cskh);

    $page = app(FrontdeskControlCenter::class);
    $page->mount(app(\App\Services\FrontdeskControlCenterService::class));
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
            'heading' => 'Lối tắt front-office',
            'grid_classes' => 'grid gap-4 md:grid-cols-2 xl:grid-cols-5',
        ])
        ->and($viewState['sections_panel']['sections'])->not->toBeEmpty()
        ->and($sections->first())->toHaveKey('empty_state_text')
        ->and($firstMetric)->not->toBeNull()
        ->and($firstMetric)->toHaveKey('badge_classes');
});
