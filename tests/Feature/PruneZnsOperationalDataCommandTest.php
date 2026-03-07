<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Support\Str;

it('prunes expired zns operational events logs and deliveries by retention days', function (): void {
    [$branch, $admin] = znsPruneAdminContext();
    $this->actingAs($admin);

    ClinicSetting::setValue('zns.retention_days', 30, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    $oldEvent = ZnsAutomationEvent::query()->create([
        'event_key' => (string) Str::uuid(),
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl_zns_prune_001',
        'branch_id' => $branch->id,
        'phone' => '0901000001',
        'normalized_phone' => '84901000001',
        'payload' => ['recipient_name' => 'Old Event'],
        'payload_checksum' => hash('sha256', 'old-zns-event'),
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'processed_at' => now()->subDays(40),
    ]);
    $oldEvent->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->saveQuietly();

    $oldLog = ZnsAutomationLog::query()->create([
        'zns_automation_event_id' => $oldEvent->id,
        'attempt' => 1,
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'http_status' => 200,
        'request_payload' => ['tracking_id' => 'old-zns-log'],
        'response_payload' => ['provider_message_id' => 'old-zns-log'],
        'attempted_at' => now()->subDays(40),
    ]);
    $oldLog->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->saveQuietly();

    $campaign = ZnsCampaign::query()->create([
        'name' => 'ZNS prune campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_COMPLETED,
        'template_id' => 'tpl_zns_prune_001',
        'finished_at' => now()->subDays(40),
        'sent_count' => 1,
    ]);

    $oldDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'phone' => '0901000002',
        'normalized_phone' => '84901000002',
        'idempotency_key' => hash('sha256', 'old-zns-delivery'),
        'status' => ZnsCampaignDelivery::STATUS_SENT,
        'payload' => ['template_id' => 'tpl_zns_prune_001'],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_prune_001',
        'sent_at' => now()->subDays(40),
    ]);
    $oldDelivery->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->saveQuietly();

    $freshEvent = ZnsAutomationEvent::query()->create([
        'event_key' => (string) Str::uuid(),
        'event_type' => ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING,
        'template_key' => 'birthday',
        'template_id_snapshot' => 'tpl_zns_prune_001',
        'branch_id' => $branch->id,
        'phone' => '0901000003',
        'normalized_phone' => '84901000003',
        'payload' => ['recipient_name' => 'Fresh Event'],
        'payload_checksum' => hash('sha256', 'fresh-zns-event'),
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'processed_at' => now()->subDays(3),
    ]);

    $freshLog = ZnsAutomationLog::query()->create([
        'zns_automation_event_id' => $freshEvent->id,
        'attempt' => 1,
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'http_status' => 200,
        'request_payload' => ['tracking_id' => 'fresh-zns-log'],
        'response_payload' => ['provider_message_id' => 'fresh-zns-log'],
        'attempted_at' => now()->subDays(3),
    ]);

    $freshDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'phone' => '0901000004',
        'normalized_phone' => '84901000004',
        'idempotency_key' => hash('sha256', 'fresh-zns-delivery'),
        'status' => ZnsCampaignDelivery::STATUS_SENT,
        'payload' => ['template_id' => 'tpl_zns_prune_001'],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_prune_001',
        'sent_at' => now()->subDays(3),
    ]);

    $this->artisan('zns:prune-operational-data')
        ->expectsOutputToContain('retention_days=30')
        ->assertSuccessful();

    expect(ZnsAutomationEvent::query()->whereKey($oldEvent->id)->exists())->toBeFalse()
        ->and(ZnsAutomationLog::query()->whereKey($oldLog->id)->exists())->toBeFalse()
        ->and(ZnsCampaignDelivery::query()->whereKey($oldDelivery->id)->exists())->toBeFalse()
        ->and(ZnsAutomationEvent::query()->whereKey($freshEvent->id)->exists())->toBeTrue()
        ->and(ZnsAutomationLog::query()->whereKey($freshLog->id)->exists())->toBeTrue()
        ->and(ZnsCampaignDelivery::query()->whereKey($freshDelivery->id)->exists())->toBeTrue();
});

it('supports dry run for zns operational data pruning', function (): void {
    [$branch, $admin] = znsPruneAdminContext();
    $this->actingAs($admin);

    $event = ZnsAutomationEvent::query()->create([
        'event_key' => (string) Str::uuid(),
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl_zns_prune_dry_run',
        'branch_id' => $branch->id,
        'phone' => '0902000001',
        'normalized_phone' => '84902000001',
        'payload' => ['recipient_name' => 'Dry run'],
        'payload_checksum' => hash('sha256', 'dry-run-event'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'processed_at' => now()->subDays(90),
    ]);

    $event->forceFill([
        'created_at' => now()->subDays(90),
        'updated_at' => now()->subDays(90),
    ])->saveQuietly();

    $this->artisan('zns:prune-operational-data', [
        '--days' => 30,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('dry_run=yes')
        ->assertSuccessful();

    expect(ZnsAutomationEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

function znsPruneAdminContext(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    return [$branch, $admin];
}
