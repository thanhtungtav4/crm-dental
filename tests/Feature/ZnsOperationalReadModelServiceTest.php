<?php

use App\Models\Branch;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsOperationalReadModelService;
use Carbon\Carbon;

it('computes zns backlog summary cards from automation, delivery, and campaign statuses', function (): void {
    $branch = Branch::factory()->create();
    $campaign = ZnsCampaign::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Campaign retry',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-01',
        'status' => ZnsCampaign::STATUS_FAILED,
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-pending',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-pending',
        'branch_id' => $branch->id,
        'phone' => '0901000001',
        'normalized_phone' => '84901000001',
        'payload' => ['recipient_name' => 'Pending'],
        'payload_checksum' => hash('sha256', 'evt-pending'),
        'status' => ZnsAutomationEvent::STATUS_PENDING,
        'max_attempts' => 3,
    ]);
    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-retry',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-retry',
        'branch_id' => $branch->id,
        'phone' => '0901000002',
        'normalized_phone' => '84901000002',
        'payload' => ['recipient_name' => 'Retry'],
        'payload_checksum' => hash('sha256', 'evt-retry'),
        'status' => ZnsAutomationEvent::STATUS_FAILED,
        'next_retry_at' => now()->subMinute(),
        'max_attempts' => 3,
    ]);
    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-dead',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-dead',
        'branch_id' => $branch->id,
        'phone' => '0901000003',
        'normalized_phone' => '84901000003',
        'payload' => ['recipient_name' => 'Dead'],
        'payload_checksum' => hash('sha256', 'evt-dead'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'max_attempts' => 3,
    ]);

    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-retry',
        'phone' => '0902000001',
        'normalized_phone' => '84902000001',
        'payload' => ['recipient_name' => 'Retry delivery'],
        'template_key_snapshot' => 'campaign_retry',
        'template_id_snapshot' => 'tpl-campaign-retry',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => now()->subMinute(),
        'attempt_count' => 1,
    ]);
    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-terminal',
        'phone' => '0902000002',
        'normalized_phone' => '84902000002',
        'payload' => ['recipient_name' => 'Terminal delivery'],
        'template_key_snapshot' => 'campaign_retry',
        'template_id_snapshot' => 'tpl-campaign-retry',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => null,
        'attempt_count' => 2,
    ]);

    $summaryCards = collect(app(ZnsOperationalReadModelService::class)->summaryCards())
        ->keyBy('label');

    expect($summaryCards->get('Automation pending')['value'])->toBe(1)
        ->and($summaryCards->get('Automation retry due')['value'])->toBe(1)
        ->and($summaryCards->get('Automation dead-letter')['value'])->toBe(1)
        ->and($summaryCards->get('Delivery retry due')['value'])->toBe(1)
        ->and($summaryCards->get('Delivery terminal failed')['value'])->toBe(1)
        ->and($summaryCards->get('Campaign failed')['value'])->toBe(1);
});

it('computes zns retention candidates from terminal automation and delivery states', function (): void {
    $branch = Branch::factory()->create();
    $campaign = ZnsCampaign::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Campaign retention',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-02',
        'status' => ZnsCampaign::STATUS_SCHEDULED,
    ]);

    $sentEvent = ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-sent-old',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-old-sent',
        'branch_id' => $branch->id,
        'phone' => '0901000101',
        'normalized_phone' => '84901000101',
        'payload' => ['recipient_name' => 'Old Sent'],
        'payload_checksum' => hash('sha256', 'evt-sent-old'),
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'processed_at' => Carbon::parse('2026-03-20 08:00:00'),
        'max_attempts' => 3,
    ]);
    $sentEvent->forceFill([
        'created_at' => Carbon::parse('2026-03-20 08:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 08:00:00'),
    ])->saveQuietly();

    $deadEvent = ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-dead-old',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-old-dead',
        'branch_id' => $branch->id,
        'phone' => '0901000102',
        'normalized_phone' => '84901000102',
        'payload' => ['recipient_name' => 'Old Dead'],
        'payload_checksum' => hash('sha256', 'evt-dead-old'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'processed_at' => null,
        'max_attempts' => 3,
    ]);
    $deadEvent->forceFill([
        'created_at' => Carbon::parse('2026-03-20 09:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 09:00:00'),
    ])->saveQuietly();

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-failed-old',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-old-failed',
        'branch_id' => $branch->id,
        'phone' => '0901000103',
        'normalized_phone' => '84901000103',
        'payload' => ['recipient_name' => 'Old Failed'],
        'payload_checksum' => hash('sha256', 'evt-failed-old'),
        'status' => ZnsAutomationEvent::STATUS_FAILED,
        'max_attempts' => 3,
    ]);

    $sentDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-sent-old',
        'phone' => '0902000101',
        'normalized_phone' => '84902000101',
        'payload' => ['recipient_name' => 'Sent old'],
        'template_key_snapshot' => 'campaign_retention',
        'template_id_snapshot' => 'tpl-campaign-retention',
        'status' => ZnsCampaignDelivery::STATUS_SENT,
        'sent_at' => Carbon::parse('2026-03-20 11:00:00'),
    ]);
    $sentDelivery->forceFill([
        'created_at' => Carbon::parse('2026-03-20 11:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 11:00:00'),
    ])->saveQuietly();

    $skippedDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-skipped-old',
        'phone' => '0902000102',
        'normalized_phone' => '84902000102',
        'payload' => ['recipient_name' => 'Skipped old'],
        'template_key_snapshot' => 'campaign_retention',
        'template_id_snapshot' => 'tpl-campaign-retention',
        'status' => ZnsCampaignDelivery::STATUS_SKIPPED,
        'sent_at' => null,
    ]);
    $skippedDelivery->forceFill([
        'created_at' => Carbon::parse('2026-03-20 12:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 12:00:00'),
    ])->saveQuietly();

    $failedTerminalDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-failed-terminal-old',
        'phone' => '0902000103',
        'normalized_phone' => '84902000103',
        'payload' => ['recipient_name' => 'Terminal old'],
        'template_key_snapshot' => 'campaign_retention',
        'template_id_snapshot' => 'tpl-campaign-retention',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => null,
        'sent_at' => null,
    ]);
    $failedTerminalDelivery->forceFill([
        'created_at' => Carbon::parse('2026-03-20 13:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 13:00:00'),
    ])->saveQuietly();

    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-failed-retrying-old',
        'phone' => '0902000104',
        'normalized_phone' => '84902000104',
        'payload' => ['recipient_name' => 'Retrying old'],
        'template_key_snapshot' => 'campaign_retention',
        'template_id_snapshot' => 'tpl-campaign-retention',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => now()->addHour(),
        'sent_at' => null,
    ]);

    $service = app(ZnsOperationalReadModelService::class);

    expect($service->automationRetentionCandidateCount(5))->toBe(2)
        ->and($service->deliveryRetentionCandidateCount(5))->toBe(3);
});
