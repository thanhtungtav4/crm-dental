<?php

use App\Filament\Pages\ZaloZns;
use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\Branch;
use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsCampaign;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('blocks doctors from accessing zns resource and page surfaces', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(ZnsCampaignResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($doctor)
        ->get(ZaloZns::getUrl())
        ->assertForbidden();
});

it('blocks cskh from accessing zns campaign surfaces', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $this->actingAs($cskh)
        ->get(ZnsCampaignResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($cskh)
        ->get(ZnsCampaignResource::getUrl('create'))
        ->assertForbidden();

    $this->actingAs($cskh)
        ->get(ZaloZns::getUrl())
        ->assertForbidden();
});

it('allows managers to access zns resource and page surfaces', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(ZnsCampaignResource::getUrl('index'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(ZaloZns::getUrl())
        ->assertOk();
});

it('renders zns operational summary and automation triage table for managers', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Campaign dashboard',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_RUNNING,
        'template_id' => 'tpl_zns_dashboard',
        'scheduled_at' => now()->subMinute(),
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'zns-dashboard-dead',
        'event_type' => ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
        'template_key' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_dashboard',
        'branch_id' => $branch->id,
        'phone' => '0909000111',
        'normalized_phone' => '84909000111',
        'payload' => ['appointment_id' => 10],
        'payload_checksum' => hash('sha256', 'zns-dashboard-dead'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'attempts' => 5,
        'max_attempts' => 5,
        'last_error' => 'Provider timeout',
        'provider_status_code' => 'timeout',
    ]);

    $this->actingAs($manager)
        ->get(ZaloZns::getUrl())
        ->assertOk()
        ->assertSee('Provider readiness')
        ->assertSee('Zalo OA')
        ->assertSee('ZNS')
        ->assertSee('Automation dead-letter')
        ->assertSee('Campaign đang chạy')
        ->assertSee('Nhắc lịch hẹn')
        ->assertSee('timeout');
});

it('builds zalo zns provider readiness from shared snapshot cards', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $dashboardSection = Livewire::test(ZaloZns::class)->instance()->dashboardViewState['dashboard_section'];
    $providerHealthSection = collect($dashboardSection['include_data']['panel']['dashboard_sections'])
        ->firstWhere('partial', 'filament.pages.partials.provider-health-panel');
    $providerHealth = collect($providerHealthSection['include_data']['panel']['items'] ?? [])->keyBy('key');

    expect($providerHealth->keys()->all())->toBe(['zalo_oa', 'zns'])
        ->and($providerHealth->get('zalo_oa'))->toHaveKeys([
            'status_badge',
            'summary_badge',
            'meta_preview',
            'status_message',
        ])
        ->and($providerHealth->get('zns'))->toHaveKeys([
            'status_badge',
            'summary_badge',
            'meta_preview',
            'status_message',
        ]);
});

it('renders zalo zns provider readiness and summary cards from shared partials', function (): void {
    $blade = File::get(resource_path('views/filament/pages/zalo-zns.blade.php'));
    $notePanelPartial = File::get(resource_path('views/filament/pages/partials/control-plane-note-panel.blade.php'));
    $providerHealthPanelPartial = File::get(resource_path('views/filament/pages/partials/provider-health-panel.blade.php'));
    $dashboardPanelPartial = File::get(resource_path('views/filament/pages/partials/zalo-zns-dashboard-panel.blade.php'));
    $summaryPanelPartial = File::get(resource_path('views/filament/pages/partials/zalo-zns-summary-panel.blade.php'));
    $notePanelsPartial = File::get(resource_path('views/filament/pages/partials/zalo-zns-note-panels.blade.php'));
    $tablePanelPartial = File::get(resource_path('views/filament/pages/partials/zalo-zns-table-panel.blade.php'));
    $partialListPartial = File::get(resource_path('views/filament/pages/partials/control-plane-partial-list.blade.php'));

    expect($blade)
        ->toContain("@include('filament.pages.partials.control-plane-section', [")
        ->not->toContain("{{ number_format(\$card['value']) }}")
        ->and($dashboardPanelPartial)
        ->toContain("@include('filament.pages.partials.control-plane-partial-list', [")
        ->toContain("'items' => \$panel['dashboard_sections']")
        ->and($partialListPartial)
        ->toContain('@foreach($items as $item)')
        ->toContain("@include(\$item['partial'], \$item['include_data'] ?? [])")
        ->and($summaryPanelPartial)
        ->toContain("@foreach(\$panel['items'] as \$card)")
        ->toContain("@include('filament.pages.partials.dashboard-summary-card', [")
        ->and($notePanelsPartial)
        ->toContain('@foreach($panels as $panel)')
        ->toContain("@include('filament.pages.partials.control-plane-note-panel', ['panel' => \$panel])")
        ->and($tablePanelPartial)
        ->toContain('{{ $this->table }}')
        ->and($providerHealthPanelPartial)
        ->toContain("\$panelBadgeLabel = \$panel['drift_badge_label'] ?? \$panel['drift_label'] ?? null;")
        ->toContain("@include('filament.pages.partials.provider-health-card'")
        ->and($notePanelPartial)
        ->toContain('@if(is_array($item))')
        ->toContain("{{ \$item['description'] }}");
});

it('builds zalo zns dashboard view state from shared contracts', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $viewState = Livewire::test(ZaloZns::class)->instance()->dashboardViewState;
    $dashboardSection = $viewState['dashboard_section'];
    $dashboardSections = $dashboardSection['include_data']['panel']['dashboard_sections'];
    $summarySection = collect($dashboardSections)->firstWhere('partial', 'filament.pages.partials.zalo-zns-summary-panel');
    $providerHealthSection = collect($dashboardSections)->firstWhere('partial', 'filament.pages.partials.provider-health-panel');
    $notePanelsSection = collect($dashboardSections)->firstWhere('partial', 'filament.pages.partials.zalo-zns-note-panels');

    expect($viewState)->toHaveKeys([
        'dashboard_section',
    ])
        ->and($dashboardSection['partial'])->toBe('filament.pages.partials.zalo-zns-dashboard-panel')
        ->and($summarySection['include_data']['panel'])->toHaveKeys([
            'items',
        ])
        ->and($providerHealthSection['include_data']['panel'])->toHaveKeys([
            'heading',
            'description',
            'drift_count',
            'drift_label',
            'items',
        ])
        ->and($providerHealthSection['include_data']['panel']['drift_label'])->toBeString()
        ->and($notePanelsSection['include_data']['panels'])->toHaveCount(2)
        ->and($dashboardSections)->toHaveCount(4)
        ->and($notePanelsSection['include_data']['panels'][0])->toHaveKeys([
            'heading',
            'items',
        ])
        ->and($notePanelsSection['include_data']['panels'][1])->toHaveKeys([
            'heading',
            'items',
        ])
        ->and($notePanelsSection['include_data']['panels'][0]['items'])->toBeArray()
        ->and($notePanelsSection['include_data']['panels'][1]['items'])->toBeArray();
});

it('blocks doctors from running zns campaign command', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor);

    expect(fn () => $this->artisan('zns:run-campaigns'))
        ->toThrow(ValidationException::class, 'Bạn không có quyền chạy campaign ZNS.');
});

it('allows managers to run zns campaign command', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->artisan('zns:run-campaigns')
        ->assertSuccessful();
});
