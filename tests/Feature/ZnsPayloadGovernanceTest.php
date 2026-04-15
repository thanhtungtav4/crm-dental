<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use App\Services\ZnsCampaignWorkflowService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('encrypts zns operational phone and payload columns while keeping search hashes', function (): void {
    $branch = Branch::factory()->create();

    $event = ZnsAutomationEvent::query()->create([
        'event_key' => (string) Str::uuid(),
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl_zns_payload_encrypt',
        'branch_id' => $branch->id,
        'phone' => '0901234567',
        'normalized_phone' => '84901234567',
        'payload' => [
            'recipient_name' => 'Tran Test',
            'lead_request_id' => 'request-zns-001',
        ],
        'payload_checksum' => hash('sha256', 'payload-zns-001'),
        'status' => ZnsAutomationEvent::STATUS_PENDING,
        'provider_response' => [
            'error' => 0,
            'message' => 'Success',
        ],
        'next_retry_at' => now()->subMinute(),
    ]);

    $delivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => ZnsCampaign::query()->create([
            'name' => 'Campaign payload encryption',
            'branch_id' => $branch->id,
            'status' => ZnsCampaign::STATUS_DRAFT,
        ])->id,
        'branch_id' => $branch->id,
        'phone' => '0901234567',
        'normalized_phone' => '84901234567',
        'idempotency_key' => hash('sha256', 'delivery-zns-001'),
        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
        'payload' => [
            'template_key' => 'appointment',
            'template_id' => 'tpl_zns_payload_encrypt',
            'message' => ['recipient_name' => 'Tran Test'],
        ],
        'provider_response' => [
            'error' => 0,
            'message' => 'Success',
        ],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_payload_encrypt',
    ]);

    $log = ZnsAutomationLog::query()->create([
        'zns_automation_event_id' => $event->id,
        'attempt' => 1,
        'status' => ZnsAutomationEvent::STATUS_SENT,
        'http_status' => 200,
        'request_payload' => [
            'phone' => '84901234567',
            'template_id' => 'tpl_zns_payload_encrypt',
        ],
        'response_payload' => [
            'error' => 0,
            'message' => 'Success',
        ],
        'attempted_at' => now(),
    ]);

    $event->refresh();
    $delivery->refresh();
    $log->refresh();

    expect($event->phone)->toBe('0901234567')
        ->and($event->normalized_phone)->toBe('84901234567')
        ->and($event->phone_search_hash)->toBe(ZnsAutomationEvent::phoneSearchHash('0901234567'))
        ->and($event->payload)->toBeArray()
        ->and($event->provider_response)->toBeArray()
        ->and($event->getRawOriginal('phone'))->not->toBe('0901234567')
        ->and($event->getRawOriginal('normalized_phone'))->not->toBe('84901234567')
        ->and($event->getRawOriginal('payload'))->not->toBe(json_encode([
            'recipient_name' => 'Tran Test',
            'lead_request_id' => 'request-zns-001',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->and($event->getRawOriginal('provider_response'))->not->toBe(json_encode([
            'error' => 0,
            'message' => 'Success',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    expect($delivery->phone)->toBe('0901234567')
        ->and($delivery->normalized_phone)->toBe('84901234567')
        ->and($delivery->phone_search_hash)->toBe(ZnsCampaignDelivery::phoneSearchHash('0901234567'))
        ->and($delivery->payload)->toBeArray()
        ->and($delivery->provider_response)->toBeArray()
        ->and($delivery->getRawOriginal('phone'))->not->toBe('0901234567')
        ->and($delivery->getRawOriginal('normalized_phone'))->not->toBe('84901234567');

    expect($log->request_payload)->toBeArray()
        ->and($log->response_payload)->toBeArray()
        ->and($log->getRawOriginal('request_payload'))->not->toBe(json_encode([
            'phone' => '84901234567',
            'template_id' => 'tpl_zns_payload_encrypt',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

it('stores sanitized provider telemetry for zns automation logs and campaign deliveries', function (): void {
    [$branch, $admin] = znsPayloadGovernanceAdminContext();

    $this->actingAs($admin);

    configureZnsPayloadGovernanceRuntime();

    $event = ZnsAutomationEvent::query()->create([
        'event_key' => (string) Str::uuid(),
        'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
        'template_key' => 'lead_welcome',
        'template_id_snapshot' => 'tpl_zns_runtime_001',
        'branch_id' => $branch->id,
        'phone' => '0908888999',
        'normalized_phone' => '84908888999',
        'payload' => [
            'recipient_name' => 'Lead Runtime',
            'lead_request_id' => 'lead-runtime-001',
            'source' => 'website',
        ],
        'payload_checksum' => hash('sha256', 'payload-runtime-001'),
        'status' => ZnsAutomationEvent::STATUS_PENDING,
        'next_retry_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => [
                'msg_id' => 'zns-msg-runtime-001',
                'debug_echo' => ['phone' => '84908888999'],
            ],
        ], 200),
    ]);

    $this->artisan('zns:sync-automation-events')->assertSuccessful();

    $event->refresh();
    $log = ZnsAutomationLog::query()
        ->where('zns_automation_event_id', $event->id)
        ->latest('id')
        ->firstOrFail();

    expect($log->request_payload)->toMatchArray([
        'template_id' => 'tpl_zns_runtime_001',
        'tracking_id' => $event->event_key,
        'campaign_code' => 'auto-lead_welcome',
        'phone_masked' => '******8999',
    ])
        ->and($log->request_payload)->toHaveKey('phone_search_hash')
        ->and($log->request_payload)->toHaveKey('template_data_keys')
        ->and($log->request_payload)->not->toHaveKey('phone')
        ->and($log->request_payload)->not->toHaveKey('template_data')
        ->and($log->response_payload)->toMatchArray([
            'error' => '0',
            'message' => 'Success',
            'provider_message_id' => 'zns-msg-runtime-001',
        ])
        ->and($log->response_payload)->not->toHaveKey('data')
        ->and($event->provider_response)->toMatchArray([
            'error' => '0',
            'message' => 'Success',
            'provider_message_id' => 'zns-msg-runtime-001',
        ])
        ->and($event->provider_response)->not->toHaveKey('data');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0906666777',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Campaign telemetry runtime',
        'branch_id' => $branch->id,
        'template_id' => 'tpl_zns_runtime_001',
        'message_payload' => [
            'recipient_name' => 'Campaign Runtime',
            'appointment_at_display' => '10/03/2026 09:00',
        ],
        'status' => ZnsCampaign::STATUS_DRAFT,
    ]);

    app(ZnsCampaignWorkflowService::class)->runNow($campaign, 'runtime governance test');

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-msg-campaign-runtime-001'],
        ], 200),
    ]);

    $result = app(ZnsCampaignRunnerService::class)->runCampaign($campaign);
    $delivery = ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign->id)->firstOrFail();
    $payload = $delivery->payload ?? [];

    expect($result['sent'])->toBe(1)
        ->and($payload)->toHaveKey('provider_request_summary')
        ->and(data_get($payload, 'provider_request_summary.phone_masked'))->toBe('******6777')
        ->and(data_get($payload, 'provider_request_summary.template_id'))->toBe('tpl_zns_runtime_001')
        ->and(data_get($payload, 'provider_request_summary.phone_search_hash'))->toBe(
            ZnsCampaignDelivery::phoneSearchHash('0906666777'),
        )
        ->and(data_get($payload, 'provider_request_summary.template_data_keys'))->toBe([
            'appointment_at_display',
            'recipient_name',
        ])
        ->and($payload)->not->toHaveKey('provider_request')
        ->and($delivery->provider_response)->toMatchArray([
            'error' => '0',
            'message' => 'Success',
            'provider_message_id' => $delivery->provider_message_id,
        ])
        ->and($delivery->provider_response)->not->toHaveKey('data');

    expect((int) ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign->id)->count())->toBe(1);

    unset($patient);
});

it('returns transitioned models for zns campaign delivery claim send and failure boundaries', function (): void {
    $branch = Branch::factory()->create();

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Campaign delivery return contract',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_DRAFT,
        'template_id' => 'tpl_zns_return_contract',
    ]);

    $failedDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'phone' => '0901111222',
        'normalized_phone' => '84901111222',
        'idempotency_key' => hash('sha256', 'delivery-zns-return-failed'),
        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
        'attempt_count' => 0,
        'payload' => [
            'message' => ['recipient_name' => 'Delivery Failed'],
        ],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_return_contract',
    ]);

    $claimedFailedDelivery = $failedDelivery->claimForProcessing('claim-token-failed');

    expect($claimedFailedDelivery)->toBe($failedDelivery)
        ->and($claimedFailedDelivery->processing_token)->toBe('claim-token-failed')
        ->and((int) $claimedFailedDelivery->attempt_count)->toBe(1)
        ->and($claimedFailedDelivery->locked_at)->not->toBeNull();

    $failedTransitionedDelivery = $claimedFailedDelivery->markFailure(
        message: 'Provider timeout',
        providerStatusCode: 429,
        providerResponse: [
            'error' => '429',
            'message' => 'Too many requests',
        ],
        nextRetryAt: now()->addMinutes(15),
        providerRequestSummary: [
            'phone_masked' => '******1222',
            'template_data_keys' => ['recipient_name'],
        ],
    );

    expect($failedTransitionedDelivery)->toBe($failedDelivery)
        ->and($failedTransitionedDelivery->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and($failedTransitionedDelivery->processing_token)->toBeNull()
        ->and($failedTransitionedDelivery->locked_at)->toBeNull()
        ->and($failedTransitionedDelivery->error_message)->toBe('Provider timeout')
        ->and(data_get($failedTransitionedDelivery->payload, 'provider_request_summary.phone_masked'))->toBe('******1222');

    $sentDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'branch_id' => $branch->id,
        'phone' => '0903333444',
        'normalized_phone' => '84903333444',
        'idempotency_key' => hash('sha256', 'delivery-zns-return-sent'),
        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
        'attempt_count' => 0,
        'payload' => [
            'message' => ['recipient_name' => 'Delivery Sent'],
        ],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_return_contract',
    ]);

    $claimedSentDelivery = $sentDelivery->claimForProcessing('claim-token-sent');

    $sentTransitionedDelivery = $claimedSentDelivery->markSent(
        providerMessageId: 'zns-message-001',
        providerStatusCode: '0',
        providerResponse: [
            'error' => '0',
            'message' => 'Success',
        ],
        providerRequestSummary: [
            'phone_masked' => '******3444',
            'template_data_keys' => ['recipient_name'],
        ],
    );

    expect($sentTransitionedDelivery)->toBe($sentDelivery)
        ->and($sentTransitionedDelivery->status)->toBe(ZnsCampaignDelivery::STATUS_SENT)
        ->and($sentTransitionedDelivery->processing_token)->toBeNull()
        ->and($sentTransitionedDelivery->locked_at)->toBeNull()
        ->and($sentTransitionedDelivery->provider_message_id)->toBe('zns-message-001')
        ->and($sentTransitionedDelivery->sent_at)->not->toBeNull()
        ->and(data_get($sentTransitionedDelivery->payload, 'provider_request_summary.phone_masked'))->toBe('******3444');
});

function znsPayloadGovernanceAdminContext(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    return [$branch, $admin];
}

function configureZnsPayloadGovernanceRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns-payload-governance-token', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns-payload-governance-refresh', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.request_timeout_seconds', 15, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('zns.campaign_delivery_max_attempts', 5, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);
}
