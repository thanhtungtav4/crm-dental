<?php

use App\Filament\Pages\ZaloZns;
use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\Branch;
use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsCampaign;
use Illuminate\Validation\ValidationException;

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
        ->assertSee('Automation dead-letter')
        ->assertSee('Campaign đang chạy')
        ->assertSee('Nhắc lịch hẹn')
        ->assertSee('timeout');
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
