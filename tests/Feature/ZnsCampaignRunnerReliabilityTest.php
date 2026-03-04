<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Support\Facades\Http;

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
}
