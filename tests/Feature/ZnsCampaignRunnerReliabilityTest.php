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
