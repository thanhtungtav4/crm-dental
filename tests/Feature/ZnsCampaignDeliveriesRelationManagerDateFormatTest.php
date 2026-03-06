<?php

use App\Models\Branch;
use App\Models\User;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('uses placeholder instead of default fallback for sent_at datetime column in zns deliveries relation manager', function (): void {
    $tableConfig = File::get(app_path('Filament/Resources/ZnsCampaigns/RelationManagers/DeliveriesRelationManager.php'));

    expect($tableConfig)
        ->toContain("TextColumn::make('sent_at')")
        ->and($tableConfig)->toContain("->dateTime('d/m/Y H:i')")
        ->and($tableConfig)->toContain("->placeholder('-')")
        ->and($tableConfig)->not->toContain("->dateTime('d/m/Y H:i')\n                    ->default('-')");
});

it('renders zns campaign edit page with deliveries relation when sent_at is null', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Campaign null sent_at',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_DRAFT,
    ]);

    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'phone' => '0900000001',
        'idempotency_key' => (string) Str::uuid(),
        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
        'sent_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.zns-campaigns.edit', ['record' => $campaign]).'?relation=deliveries')
        ->assertSuccessful()
        ->assertSee('Campaign null sent_at');
});
