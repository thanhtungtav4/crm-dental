<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Support\Facades\Http;

it('skips campaign that is actively locked by another runner', function (): void {
    configureZnsCampaignRunnerLockRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0903555666',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Locked campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_id' => 'tpl_zns_locked_campaign',
        'scheduled_at' => now()->subMinute(),
    ]);
    $campaign->forceFill([
        'processing_token' => 'existing-runner-token',
        'locked_at' => now(),
    ])->save();

    Http::fake();

    $result = app(ZnsCampaignRunnerService::class)->runCampaign($campaign);
    $campaign = $campaign->fresh();

    expect($result)->toBe([
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
    ])->and($campaign)->not->toBeNull()
        ->and($campaign?->processing_token)->toBe('existing-runner-token')
        ->and((int) ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign?->id)->count())->toBe(0);

    Http::assertNothingSent();
});

it('reclaims stale campaign lock and finishes processing', function (): void {
    configureZnsCampaignRunnerLockRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0903999888',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Stale locked campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_RUNNING,
        'template_id' => 'tpl_zns_stale_campaign',
        'scheduled_at' => now()->subMinute(),
    ]);
    $campaign->forceFill([
        'processing_token' => 'stale-runner-token',
        'locked_at' => now()->subMinutes(ZnsCampaign::PROCESSING_LOCK_TTL_MINUTES + 1),
    ])->save();

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-campaign-lock-reclaimed-001'],
        ], 200),
    ]);

    $result = app(ZnsCampaignRunnerService::class)->runCampaign($campaign);
    $campaign = $campaign->fresh();
    $delivery = ZnsCampaignDelivery::query()->first();

    expect($result['processed'])->toBe(1)
        ->and($result['sent'])->toBe(1)
        ->and($campaign)->not->toBeNull()
        ->and($campaign?->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and($campaign?->processing_token)->toBeNull()
        ->and($campaign?->locked_at)->toBeNull()
        ->and($delivery)->not->toBeNull()
        ->and($delivery?->status)->toBe(ZnsCampaignDelivery::STATUS_SENT);

    Http::assertSentCount(1);
});

it('run command skips campaigns that are actively locked by another runner', function (): void {
    configureZnsCampaignRunnerLockRuntime();

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0903111222',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Command locked campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_id' => 'tpl_zns_command_locked',
        'scheduled_at' => now()->subMinute(),
    ]);
    $campaign->forceFill([
        'processing_token' => 'worker-running-command-lock',
        'locked_at' => now(),
    ])->save();

    Http::fake();

    $this->artisan('zns:run-campaigns')
        ->assertSuccessful();

    $campaign = $campaign->fresh();

    expect($campaign)->not->toBeNull()
        ->and($campaign?->processing_token)->toBe('worker-running-command-lock')
        ->and((int) ZnsCampaignDelivery::query()->where('zns_campaign_id', $campaign?->id)->count())->toBe(0);

    Http::assertNothingSent();
});

function configureZnsCampaignRunnerLockRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns_access_token_runner_lock', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns_refresh_token_runner_lock', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.template_appointment', 'tpl_zns_runner_lock', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);
}
