<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Support\Facades\Http;

it('picks failed campaigns only when they have due retry deliveries', function (): void {
    configureZnsCommandRuntime();

    $retryBranch = Branch::factory()->create();
    $terminalBranch = Branch::factory()->create();

    $retryPatient = Patient::factory()->create([
        'first_branch_id' => $retryBranch->id,
        'phone' => '0901111000',
    ]);
    $terminalPatient = Patient::factory()->create([
        'first_branch_id' => $terminalBranch->id,
        'phone' => '0902222000',
    ]);

    $retryCampaign = ZnsCampaign::query()->create([
        'name' => 'Retry campaign',
        'branch_id' => $retryBranch->id,
        'status' => ZnsCampaign::STATUS_FAILED,
        'template_id' => 'tpl_zns_retry',
        'scheduled_at' => now()->subMinute(),
        'started_at' => null,
    ]);

    $terminalCampaign = ZnsCampaign::query()->create([
        'name' => 'Terminal campaign',
        'branch_id' => $terminalBranch->id,
        'status' => ZnsCampaign::STATUS_FAILED,
        'template_id' => 'tpl_zns_retry',
        'scheduled_at' => now()->subMinute(),
        'started_at' => null,
    ]);

    $retryPhone = normalizePhoneForZnsCampaignTest((string) $retryPatient->phone);
    $terminalPhone = normalizePhoneForZnsCampaignTest((string) $terminalPatient->phone);

    $retryDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $retryCampaign->id,
        'patient_id' => $retryPatient->id,
        'customer_id' => $retryPatient->customer_id,
        'branch_id' => $retryBranch->id,
        'phone' => $retryPhone,
        'normalized_phone' => $retryPhone,
        'idempotency_key' => znsCampaignDeliveryIdempotencyKey($retryCampaign->id, $retryPhone, 'tpl_zns_retry'),
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'attempt_count' => 1,
        'next_retry_at' => now()->subMinute(),
        'payload' => ['template_id' => 'tpl_zns_retry'],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_retry',
    ]);

    $terminalDelivery = ZnsCampaignDelivery::query()->create([
        'zns_campaign_id' => $terminalCampaign->id,
        'patient_id' => $terminalPatient->id,
        'customer_id' => $terminalPatient->customer_id,
        'branch_id' => $terminalBranch->id,
        'phone' => $terminalPhone,
        'normalized_phone' => $terminalPhone,
        'idempotency_key' => znsCampaignDeliveryIdempotencyKey($terminalCampaign->id, $terminalPhone, 'tpl_zns_retry'),
        'status' => ZnsCampaignDelivery::STATUS_FAILED,
        'attempt_count' => 1,
        'next_retry_at' => null,
        'payload' => ['template_id' => 'tpl_zns_retry'],
        'template_key_snapshot' => 'appointment',
        'template_id_snapshot' => 'tpl_zns_retry',
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-run-campaigns-retry-001'],
        ], 200),
    ]);

    $this->artisan('zns:run-campaigns')
        ->assertSuccessful();

    Http::assertSentCount(1);

    $retryDelivery = $retryDelivery->fresh();
    $terminalDelivery = $terminalDelivery->fresh();
    $retryCampaign = $retryCampaign->fresh();
    $terminalCampaign = $terminalCampaign->fresh();

    expect($retryDelivery)->not->toBeNull()
        ->and($retryDelivery?->status)->toBe(ZnsCampaignDelivery::STATUS_SENT)
        ->and((int) $retryDelivery?->attempt_count)->toBe(2)
        ->and($retryCampaign)->not->toBeNull()
        ->and($retryCampaign?->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and($retryCampaign?->started_at)->not->toBeNull()
        ->and($terminalDelivery)->not->toBeNull()
        ->and($terminalDelivery?->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
        ->and((int) $terminalDelivery?->attempt_count)->toBe(1)
        ->and($terminalCampaign)->not->toBeNull()
        ->and($terminalCampaign?->status)->toBe(ZnsCampaign::STATUS_FAILED)
        ->and($terminalCampaign?->started_at)->toBeNull();
});

it('continues processing other campaigns when one campaign fails validation', function (): void {
    configureZnsCommandRuntime();
    ClinicSetting::setValue('zns.template_payment', '', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    $branch = Branch::factory()->create();
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0903333444',
    ]);

    $invalidCampaign = ZnsCampaign::query()->create([
        'name' => 'Invalid template campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_key' => 'payment',
        'template_id' => '',
        'scheduled_at' => now()->subMinute(),
    ]);

    $validCampaign = ZnsCampaign::query()->create([
        'name' => 'Valid template campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'template_id' => 'tpl_zns_valid_001',
        'scheduled_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-valid-campaign-001'],
        ], 200),
    ]);

    $this->artisan('zns:run-campaigns')
        ->assertFailed();

    Http::assertSentCount(1);

    $invalidCampaign = $invalidCampaign->fresh();
    $validCampaign = $validCampaign->fresh();

    expect($invalidCampaign)->not->toBeNull()
        ->and($invalidCampaign?->status)->toBe(ZnsCampaign::STATUS_FAILED)
        ->and($validCampaign)->not->toBeNull()
        ->and($validCampaign?->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and((int) $validCampaign?->sent_count)->toBe(1)
        ->and((int) ZnsCampaignDelivery::query()->where('zns_campaign_id', $validCampaign?->id)->count())->toBe(1);
});

function configureZnsCommandRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns_access_token_command', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns_refresh_token_command', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.template_appointment', 'tpl_zns_retry', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);
}

function normalizePhoneForZnsCampaignTest(string $phone): string
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

function znsCampaignDeliveryIdempotencyKey(int $campaignId, string $normalizedPhone, string $templateId): string
{
    return hash('sha256', implode('|', [
        'zns-v2',
        $campaignId,
        $normalizedPhone !== '' ? $normalizedPhone : 'missing-phone',
        $templateId,
    ]));
}
