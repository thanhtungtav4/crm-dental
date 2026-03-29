<?php

use App\Models\Branch;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
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

    ZnsAutomationLog::query()->create([
        'zns_automation_event_id' => $sentEvent->id,
        'attempt' => 1,
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'request_payload' => ['event_key' => 'evt-sent-old'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => Carbon::parse('2026-03-20 07:30:00'),
    ]);

    ZnsAutomationLog::query()->create([
        'zns_automation_event_id' => $deadEvent->id,
        'attempt' => 1,
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'request_payload' => ['event_key' => 'evt-dead-old'],
        'response_payload' => ['message' => 'dead'],
        'attempted_at' => now()->subDay(),
    ]);

    $service = app(ZnsOperationalReadModelService::class);

    expect($service->automationRetentionCandidateCount(5))->toBe(2)
        ->and($service->automationLogRetentionCandidateCount(5))->toBe(1)
        ->and($service->deliveryRetentionCandidateCount(5))->toBe(3);
});

it('supports scoped dead-letter counts by zns event type', function (): void {
    $branch = Branch::factory()->create();

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-dead-lead',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-dead-lead',
        'branch_id' => $branch->id,
        'phone' => '0903000001',
        'normalized_phone' => '84903000001',
        'payload' => ['recipient_name' => 'Dead Lead'],
        'payload_checksum' => hash('sha256', 'evt-dead-lead'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'max_attempts' => 3,
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-dead-birthday',
        'event_type' => ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING,
        'template_key' => 'birthday',
        'template_id_snapshot' => 'tpl-dead-birthday',
        'branch_id' => $branch->id,
        'phone' => '0903000002',
        'normalized_phone' => '84903000002',
        'payload' => ['recipient_name' => 'Dead Birthday'],
        'payload_checksum' => hash('sha256', 'evt-dead-birthday'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'max_attempts' => 3,
    ]);

    expect(app(ZnsOperationalReadModelService::class)->automationDeadCount())->toBe(2)
        ->and(app(ZnsOperationalReadModelService::class)->automationDeadCount(ZnsAutomationEvent::EVENT_LEAD_WELCOME))->toBe(1)
        ->and(app(ZnsOperationalReadModelService::class)->automationDeadCount(ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING))->toBe(1);
});

it('builds scoped zns summary metrics and provider status options through the shared read model', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    ZnsCampaign::query()->create([
        'branch_id' => $branchA->id,
        'name' => 'Campaign running A',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-A',
        'status' => ZnsCampaign::STATUS_RUNNING,
    ]);

    ZnsCampaign::query()->create([
        'branch_id' => $branchB->id,
        'name' => 'Campaign running B',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-B',
        'status' => ZnsCampaign::STATUS_RUNNING,
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-summary-a',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-summary-a',
        'branch_id' => $branchA->id,
        'phone' => '0903000001',
        'normalized_phone' => '84903000001',
        'payload' => ['recipient_name' => 'Summary A'],
        'payload_checksum' => hash('sha256', 'evt-summary-a'),
        'status' => ZnsAutomationEvent::STATUS_FAILED,
        'provider_status_code' => 'E100',
        'next_retry_at' => now()->subMinute(),
        'max_attempts' => 3,
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-summary-b',
        'event_type' => ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
        'template_key' => 'appointment_reminder',
        'template_id_snapshot' => 'tpl-summary-b',
        'branch_id' => $branchB->id,
        'phone' => '0903000002',
        'normalized_phone' => '84903000002',
        'payload' => ['recipient_name' => 'Summary B'],
        'payload_checksum' => hash('sha256', 'evt-summary-b'),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'provider_status_code' => 'E200',
        'max_attempts' => 3,
    ]);

    $service = app(ZnsOperationalReadModelService::class);

    expect($service->summaryMetrics([$branchA->id]))->toMatchArray([
        'automation_pending' => 0,
        'automation_retry_due' => 1,
        'automation_dead' => 0,
        'deliveries_retry_due' => 0,
        'deliveries_terminal_failed' => 0,
        'campaigns_running' => 1,
        'campaigns_failed' => 0,
    ]);

    expect($service->automationProviderStatusOptions([$branchA->id]))->toBe([
        'E100' => 'E100',
    ]);
});

it('supports branch-scoped zns backlog summary cards', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $campaign = ZnsCampaign::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Campaign scoped primary',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-SCOPE-01',
        'status' => ZnsCampaign::STATUS_FAILED,
    ]);
    $otherCampaign = ZnsCampaign::query()->create([
        'branch_id' => $otherBranch->id,
        'name' => 'Campaign scoped other',
        'audience_source' => 'manual',
        'template_key' => 'birthday',
        'template_id' => 'TPL-SCOPE-02',
        'status' => ZnsCampaign::STATUS_FAILED,
    ]);

    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-scope-pending-primary',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-scope-pending-primary',
        'branch_id' => $branch->id,
        'phone' => '0903000101',
        'normalized_phone' => '84903000101',
        'payload' => ['recipient_name' => 'Primary pending'],
        'payload_checksum' => hash('sha256', 'evt-scope-pending-primary'),
        'status' => ZnsAutomationEvent::STATUS_PENDING,
        'max_attempts' => 3,
    ]);
    ZnsAutomationEvent::query()->create([
        'event_key' => 'evt-scope-pending-other',
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl-scope-pending-other',
        'branch_id' => $otherBranch->id,
        'phone' => '0903000102',
        'normalized_phone' => '84903000102',
        'payload' => ['recipient_name' => 'Other pending'],
        'payload_checksum' => hash('sha256', 'evt-scope-pending-other'),
        'status' => ZnsAutomationEvent::STATUS_PENDING,
        'max_attempts' => 3,
    ]);

    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'idempotency_key' => 'delivery-scope-primary',
        'phone' => '0903000201',
        'normalized_phone' => '84903000201',
        'payload' => ['recipient_name' => 'Primary retry delivery'],
        'template_key_snapshot' => 'campaign_scope_primary',
        'template_id_snapshot' => 'tpl-campaign-scope-primary',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => now()->subMinute(),
        'attempt_count' => 1,
    ]);
    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $otherCampaign->id,
        'branch_id' => $otherBranch->id,
        'idempotency_key' => 'delivery-scope-other',
        'phone' => '0903000202',
        'normalized_phone' => '84903000202',
        'payload' => ['recipient_name' => 'Other retry delivery'],
        'template_key_snapshot' => 'campaign_scope_other',
        'template_id_snapshot' => 'tpl-campaign-scope-other',
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'next_retry_at' => now()->subMinute(),
        'attempt_count' => 1,
    ]);

    $summaryCards = collect(app(ZnsOperationalReadModelService::class)->summaryCards([$branch->id]))
        ->keyBy('label');

    expect($summaryCards->get('Automation pending')['value'])->toBe(1)
        ->and($summaryCards->get('Delivery retry due')['value'])->toBe(1)
        ->and($summaryCards->get('Campaign failed')['value'])->toBe(1);
});
