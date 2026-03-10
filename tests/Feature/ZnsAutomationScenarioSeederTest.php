<?php

use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Models\ZnsCampaignDelivery;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\ZnsAutomationScenarioSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\seed;

it('creates a reclaimable zns automation event that syncs successfully after stale lock recovery', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.ident.test')->firstOrFail();
    $this->actingAs($admin);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-demo-reclaim-001'],
        ], 200),
    ]);

    $this->artisan('zns:sync-automation-events', [
        '--event_type' => ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
    ])->assertSuccessful();

    $event = ZnsAutomationEvent::query()
        ->where('event_key', ZnsAutomationScenarioSeeder::RECLAIMABLE_EVENT_KEY)
        ->firstOrFail();

    $log = ZnsAutomationLog::query()
        ->where('zns_automation_event_id', $event->id)
        ->latest('id')
        ->firstOrFail();

    expect($event->status)->toBe(ZnsAutomationEvent::STATUS_SENT)
        ->and($event->attempts)->toBe(2)
        ->and($event->locked_at)->toBeNull()
        ->and($event->provider_message_id)->toBe('zns-demo-reclaim-001')
        ->and($log->status)->toBe(ZnsAutomationEvent::STATUS_SENT);
});

it('creates zns prune scenarios that delete old events and deliveries while preserving fresh records', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.ident.test')->firstOrFail();
    $this->actingAs($admin);

    $deadEventId = ZnsAutomationEvent::query()
        ->where('event_key', ZnsAutomationScenarioSeeder::DEAD_OLD_EVENT_KEY)
        ->value('id');
    $sentOldEventId = ZnsAutomationEvent::query()
        ->where('event_key', ZnsAutomationScenarioSeeder::SENT_OLD_EVENT_KEY)
        ->value('id');
    $freshEventId = ZnsAutomationEvent::query()
        ->where('event_key', ZnsAutomationScenarioSeeder::SENT_FRESH_EVENT_KEY)
        ->value('id');
    $oldSentDeliveryId = ZnsCampaignDelivery::query()
        ->where('idempotency_key', hash('sha256', ZnsAutomationScenarioSeeder::OLD_SENT_DELIVERY_KEY))
        ->value('id');
    $oldFailedDeliveryId = ZnsCampaignDelivery::query()
        ->where('idempotency_key', hash('sha256', ZnsAutomationScenarioSeeder::OLD_FAILED_DELIVERY_KEY))
        ->value('id');
    $freshDeliveryId = ZnsCampaignDelivery::query()
        ->where('idempotency_key', hash('sha256', ZnsAutomationScenarioSeeder::FRESH_SENT_DELIVERY_KEY))
        ->value('id');

    $this->artisan('zns:prune-operational-data', [
        '--days' => 30,
    ])
        ->expectsOutputToContain('dry_run=no')
        ->assertSuccessful();

    expect(ZnsAutomationEvent::query()->whereKey($deadEventId)->exists())->toBeFalse()
        ->and(ZnsAutomationEvent::query()->whereKey($sentOldEventId)->exists())->toBeFalse()
        ->and(ZnsAutomationEvent::query()->whereKey($freshEventId)->exists())->toBeTrue()
        ->and(ZnsAutomationLog::query()->where('zns_automation_event_id', $deadEventId)->exists())->toBeFalse()
        ->and(ZnsAutomationLog::query()->where('zns_automation_event_id', $sentOldEventId)->exists())->toBeFalse()
        ->and(ZnsCampaignDelivery::query()->whereKey($oldSentDeliveryId)->exists())->toBeFalse()
        ->and(ZnsCampaignDelivery::query()->whereKey($oldFailedDeliveryId)->exists())->toBeFalse()
        ->and(ZnsCampaignDelivery::query()->whereKey($freshDeliveryId)->exists())->toBeTrue();
});
