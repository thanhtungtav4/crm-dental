<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('does not retry terminal failed deliveries with null next_retry_at on rerun', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Terminal failed campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake();

    $runner = app(ZnsCampaignRunnerService::class);
    $firstRun = $runner->runCampaign($campaign);
    $campaign->refresh();

    $delivery = ZnsCampaignDelivery::query()->first();

    expect($firstRun['failed'])->toBe(1)
        ->and($delivery)->not->toBeNull()
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and($delivery?->next_retry_at)->toBeNull()
        ->and((int) $delivery?->attempt_count)->toBe(1)
        ->and($campaign->status)->toBe(ZnsCampaign::STATUS_FAILED);

    $secondRun = $runner->runCampaign($campaign->fresh());
    $delivery = $delivery?->fresh();

    expect($secondRun['processed'])->toBe(0)
        ->and($secondRun['failed'])->toBe(0)
        ->and($secondRun['skipped'])->toBe(1)
        ->and((int) $delivery?->attempt_count)->toBe(1)
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED);
});

it('marks 4xx provider failures as terminal and skips them on next run', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0908888999',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => '4xx failure campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 100,
            'message' => 'Invalid template data',
        ], 400),
    ]);

    $runner = app(ZnsCampaignRunnerService::class);
    $firstRun = $runner->runCampaign($campaign);
    $delivery = ZnsCampaignDelivery::query()->first();

    expect($firstRun['failed'])->toBe(1)
        ->and($delivery)->not->toBeNull()
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and($delivery?->next_retry_at)->toBeNull()
        ->and((int) $delivery?->attempt_count)->toBe(1);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'should-not-send'],
        ], 200),
    ]);

    $secondRun = $runner->runCampaign($campaign->fresh());
    $delivery = $delivery?->fresh();

    expect($secondRun['processed'])->toBe(0)
        ->and($secondRun['failed'])->toBe(0)
        ->and($secondRun['skipped'])->toBe(1)
        ->and((int) $delivery?->attempt_count)->toBe(1)
        ->and($delivery?->provider_message_id)->toBeNull();
});

it('keeps 5xx provider failures retryable with scheduled next_retry_at', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0907777666',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => '5xx retryable campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 500,
            'message' => 'Temporary upstream error',
        ], 500),
    ]);

    $runner = app(ZnsCampaignRunnerService::class);
    $firstRun = $runner->runCampaign($campaign);
    $delivery = ZnsCampaignDelivery::query()->first();

    expect($firstRun['failed'])->toBe(1)
        ->and($delivery)->not->toBeNull()
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and($delivery?->next_retry_at)->not->toBeNull()
        ->and($delivery?->next_retry_at?->isFuture())->toBeTrue();
});

it('stops retrying failed deliveries after reaching campaign max attempts', function (): void {
    configureZnsCampaignRuntime();
    ClinicSetting::setValue('zns.campaign_delivery_max_attempts', 2, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0907111222',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Capped retry campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 500,
            'message' => 'Temporary upstream error',
        ], 500),
    ]);

    $runner = app(ZnsCampaignRunnerService::class);
    $firstRun = $runner->runCampaign($campaign);

    $delivery = ZnsCampaignDelivery::query()->first();

    expect($firstRun['failed'])->toBe(1)
        ->and($delivery)->not->toBeNull()
        ->and((int) $delivery?->attempt_count)->toBe(1)
        ->and($delivery?->next_retry_at)->not->toBeNull();

    $delivery?->forceFill([
        'next_retry_at' => now()->subMinute(),
    ])->save();

    $secondRun = $runner->runCampaign($campaign->fresh());
    $delivery = $delivery?->fresh();

    expect($secondRun['processed'])->toBe(1)
        ->and($secondRun['failed'])->toBe(1)
        ->and((int) $delivery?->attempt_count)->toBe(2)
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and($delivery?->next_retry_at)->toBeNull();

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'should-not-send'],
        ], 200),
    ]);

    $thirdRun = $runner->runCampaign($campaign->fresh());
    $delivery = $delivery?->fresh();

    expect($thirdRun['processed'])->toBe(0)
        ->and($thirdRun['failed'])->toBe(0)
        ->and($thirdRun['skipped'])->toBe(1)
        ->and((int) $delivery?->attempt_count)->toBe(2)
        ->and($delivery?->provider_message_id)->toBeNull();
});

it('processes entire audience when campaign has more than 500 eligible patients', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();

    for ($index = 1; $index <= 520; $index++) {
        Patient::factory()->create([
            'first_branch_id' => $branch->id,
            'phone' => '09'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
        ]);
    }

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Large audience campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_id' => 'tpl_large_audience_001',
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'bulk-sent'],
        ], 200),
    ]);

    $result = app(ZnsCampaignRunnerService::class)->runCampaign($campaign);
    $campaign = $campaign->fresh();

    expect($result['processed'])->toBe(520)
        ->and($result['sent'])->toBe(520)
        ->and($result['failed'])->toBe(0)
        ->and($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and((int) $campaign?->sent_count)->toBe(520)
        ->and((int) $campaign?->failed_count)->toBe(0)
        ->and((int) ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign?->id)->count())->toBe(520);

    Http::assertSentCount(520);
});

it('skips delivery that is actively claimed by another worker', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0908999888',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Concurrent claim campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_id' => 'tpl_concurrent_001',
        'scheduled_at' => now()->subMinute(),
    ]);

    $normalizedPhone = normalizePhoneForZnsCampaignReliabilityTest((string) $patient->phone);

    ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $campaign->id,
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'branch_id' => $branch->id,
        'phone' => (string) $patient->phone,
        'normalized_phone' => $normalizedPhone,
        'idempotency_key' => znsCampaignDeliveryIdempotencyKeyForReliability($campaign->id, $normalizedPhone, 'tpl_concurrent_001'),
        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
        'processing_token' => 'worker-active-token',
        'locked_at' => now(),
        'attempt_count' => 1,
        'payload' => ['template_id' => 'tpl_concurrent_001'],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_concurrent_001',
    ]);

    Http::fake();

    $result = app(ZnsCampaignRunnerService::class)->runCampaign($campaign);
    $campaign = $campaign->fresh();
    $delivery = ZnsCampaignDelivery::query()->first();

    expect($result['processed'])->toBe(0)
        ->and($result['sent'])->toBe(0)
        ->and($result['failed'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_RUNNING)
        ->and($campaign?->finished_at)->toBeNull()
        ->and($delivery)->not->toBeNull()
        ->and($delivery?->processing_token)->toBe('worker-active-token');

    Http::assertNothingSent();
});

it('keeps campaign in failed status while retryable deliveries still exist even if some are sent', function (): void {
    configureZnsCampaignRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0907000001',
    ]);
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0907000002',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Mixed retryable campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    $providerAttempt = 0;
    Http::fake([
        'https://business.openapi.zalo.me/*' => function () use (&$providerAttempt) {
            $providerAttempt++;

            if ($providerAttempt === 1) {
                return Http::response([
                    'error' => 0,
                    'message' => 'Success',
                    'data' => ['msg_id' => 'mixed-sent-001'],
                ], 200);
            }

            if (in_array($providerAttempt, [2, 3, 4], true)) {
                return Http::response([
                    'error' => 500,
                    'message' => 'Temporary upstream error',
                ], 500);
            }

            return Http::response([
                'error' => 0,
                'message' => 'Success',
                'data' => ['msg_id' => 'mixed-sent-002'],
            ], 200);
        },
    ]);

    $runner = app(ZnsCampaignRunnerService::class);
    $firstRun = $runner->runCampaign($campaign);
    $campaign = $campaign->fresh();

    $failedDelivery = ZnsCampaignDelivery::query()
        ->where('zns_campaign_id', $campaign?->id)
        ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
        ->first();

    expect($firstRun['sent'])->toBe(1)
        ->and($firstRun['failed'])->toBe(1)
        ->and($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_FAILED)
        ->and((int) $campaign?->sent_count)->toBe(1)
        ->and((int) $campaign?->failed_count)->toBe(1)
        ->and($failedDelivery)->not->toBeNull()
        ->and($failedDelivery?->next_retry_at)->not->toBeNull();

    $failedDelivery?->forceFill([
        'next_retry_at' => now()->subMinute(),
    ])->save();

    $secondRun = $runner->runCampaign($campaign?->fresh() ?? $campaign);
    $campaign = $campaign?->fresh();

    expect($secondRun['processed'])->toBe(1)
        ->and($secondRun['sent'])->toBe(1)
        ->and($secondRun['failed'])->toBe(0)
        ->and($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and((int) $campaign?->sent_count)->toBe(2)
        ->and((int) $campaign?->failed_count)->toBe(0);
});

it('marks campaign as failed when template validation fails before delivery processing', function (): void {
    configureZnsCampaignRuntime();
    ClinicSetting::setValue('zns.template_payment', '', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0907333444',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Template missing campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_key' => 'payment',
        'template_id' => '',
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake();

    expect(fn (): array => app(ZnsCampaignRunnerService::class)->runCampaign($campaign))
        ->toThrow(ValidationException::class);

    $campaign = $campaign->fresh();

    expect($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_FAILED)
        ->and($campaign?->finished_at)->not->toBeNull()
        ->and(ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign?->id)->count())->toBe(0);
});

function configureZnsCampaignRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns_access_token_reliability', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns_refresh_token_reliability', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.template_appointment', 'tpl_appointment_reliability', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.campaign_delivery_max_attempts', 5, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);
}

function znsCampaignDeliveryIdempotencyKeyForReliability(int $campaignId, string $normalizedPhone, string $templateId): string
{
    return hash('sha256', implode('|', [
        'zns-v2',
        $campaignId,
        $normalizedPhone !== '' ? $normalizedPhone : 'missing-phone',
        $templateId,
    ]));
}

function normalizePhoneForZnsCampaignReliabilityTest(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (! is_string($digits)) {
        return '';
    }

    $digits = trim($digits);
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '00')) {
        $digits = ltrim(substr($digits, 2), '0');
    }

    if (str_starts_with($digits, '0')) {
        $digits = '84'.substr($digits, 1);
    } elseif (str_starts_with($digits, '84')) {
        $digits = '84'.ltrim(substr($digits, 2), '0');
    }

    if (! str_starts_with($digits, '84')) {
        return '';
    }

    $length = strlen($digits);
    if ($length < 10 || $length > 12) {
        return '';
    }

    return $digits;
}
