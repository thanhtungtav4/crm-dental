<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Validation\ValidationException;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeZnsDeliveryFixture(string $status = ZnsCampaignDelivery::STATUS_QUEUED): array
{
    $branch = Branch::factory()->create();

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0908111222',
    ]);

    $campaign = ZnsCampaign::query()->create([
        'name' => 'Test ZNS Campaign',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_RUNNING,
    ]);

    $delivery = ZnsCampaignDelivery::runWithinManagedWorkflow(function () use ($campaign, $patient, $branch, $status): ZnsCampaignDelivery {
        return ZnsCampaignDelivery::query()->create([
            'zns_campaign_id' => $campaign->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'phone' => '0908111222',
            'normalized_phone' => '84908111222',
            'idempotency_key' => 'idem_'.uniqid(),
            'status' => $status,
        ]);
    });

    return [$campaign, $delivery, $patient, $branch];
}

function configureZnsDeliveryFlowRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns_flow_test_token', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.template_appointment', 'tpl_flow_test', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);
}

// ---------------------------------------------------------------------------
// Status guard tests
// ---------------------------------------------------------------------------

describe('ZnsCampaignDelivery — status guard', function (): void {
    it('blocks direct status write outside managed workflow', function (): void {
        [, $delivery] = makeZnsDeliveryFixture();

        expect(fn () => $delivery->forceFill(['status' => ZnsCampaignDelivery::STATUS_SENT])->save())
            ->toThrow(ValidationException::class);
    });

    it('allows status write inside runWithinManagedWorkflow', function (): void {
        [, $delivery] = makeZnsDeliveryFixture();

        ZnsCampaignDelivery::runWithinManagedWorkflow(function () use ($delivery): void {
            $delivery->forceFill(['status' => ZnsCampaignDelivery::STATUS_SENT])->save();
        });

        expect($delivery->fresh()->status)->toBe(ZnsCampaignDelivery::STATUS_SENT);
    });

    it('blocks invalid status transitions even inside managed workflow', function (): void {
        [, $delivery] = makeZnsDeliveryFixture(ZnsCampaignDelivery::STATUS_SENT);

        expect(fn () => ZnsCampaignDelivery::runWithinManagedWorkflow(function () use ($delivery): void {
            $delivery->forceFill(['status' => ZnsCampaignDelivery::STATUS_QUEUED])->save();
        }))->toThrow(ValidationException::class);
    });
});

// ---------------------------------------------------------------------------
// markSent — happy path
// ---------------------------------------------------------------------------

describe('ZnsCampaignDelivery — markSent', function (): void {
    it('transitions queued → sent via markSent and writes audit with trigger zns_sent', function (): void {
        [$campaign, $delivery, $patient, $branch] = makeZnsDeliveryFixture();

        $delivery->markSent(
            providerMessageId: 'msg_abc123',
            providerStatusCode: 200,
            providerResponse: ['error' => 0, 'message' => 'Success'],
        );

        $delivery->refresh();

        expect($delivery->status)->toBe(ZnsCampaignDelivery::STATUS_SENT)
            ->and($delivery->provider_message_id)->toBe('msg_abc123')
            ->and($delivery->sent_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->where('action', AuditLog::ACTION_RUN)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('zns_sent')
            ->and($log->metadata['status_from'])->toBe(ZnsCampaignDelivery::STATUS_QUEUED)
            ->and($log->metadata['status_to'])->toBe(ZnsCampaignDelivery::STATUS_SENT)
            ->and($log->metadata['delivery_id'])->toBe((int) $delivery->id)
            ->and($log->metadata['provider_message_id'])->toBe('msg_abc123')
            ->and($log->branch_id)->toBe($branch->id)
            ->and($log->patient_id)->toBe($patient->id);
    });

    it('can retry a failed delivery and transition failed → sent with audit', function (): void {
        [$campaign, $delivery] = makeZnsDeliveryFixture(ZnsCampaignDelivery::STATUS_FAILED);

        $delivery->markSent(
            providerMessageId: 'msg_retry_ok',
            providerStatusCode: 200,
            providerResponse: null,
        );

        $delivery->refresh();

        expect($delivery->status)->toBe(ZnsCampaignDelivery::STATUS_SENT);

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->where('action', AuditLog::ACTION_RUN)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('zns_sent')
            ->and($log->metadata['status_from'])->toBe(ZnsCampaignDelivery::STATUS_FAILED)
            ->and($log->metadata['status_to'])->toBe(ZnsCampaignDelivery::STATUS_SENT);
    });
});

// ---------------------------------------------------------------------------
// markFailure — retryable and dead-letter paths
// ---------------------------------------------------------------------------

describe('ZnsCampaignDelivery — markFailure', function (): void {
    it('transitions queued → failed (retryable) and writes audit with trigger zns_retryable', function (): void {
        [$campaign, $delivery, $patient, $branch] = makeZnsDeliveryFixture();

        $nextRetryAt = now()->addMinutes(15);

        $delivery->markFailure(
            message: 'Provider timeout',
            providerStatusCode: 503,
            providerResponse: null,
            nextRetryAt: $nextRetryAt,
        );

        $delivery->refresh();

        expect($delivery->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
            ->and($delivery->next_retry_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->where('action', AuditLog::ACTION_FAIL)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('zns_retryable')
            ->and($log->metadata['status_from'])->toBe(ZnsCampaignDelivery::STATUS_QUEUED)
            ->and($log->metadata['status_to'])->toBe(ZnsCampaignDelivery::STATUS_FAILED)
            ->and($log->metadata['error_message'])->toBe('Provider timeout')
            ->and($log->branch_id)->toBe($branch->id)
            ->and($log->patient_id)->toBe($patient->id);
    });

    it('transitions queued → failed (dead-letter) and writes audit with trigger zns_dead', function (): void {
        [$campaign, $delivery] = makeZnsDeliveryFixture();

        $delivery->markFailure(
            message: 'Invalid template data',
            providerStatusCode: 400,
            providerResponse: ['error' => 100, 'message' => 'Bad request'],
            nextRetryAt: null,
        );

        $delivery->refresh();

        expect($delivery->status)->toBe(ZnsCampaignDelivery::STATUS_FAILED)
            ->and($delivery->next_retry_at)->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->where('action', AuditLog::ACTION_FAIL)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('zns_dead')
            ->and($log->metadata['status_to'])->toBe(ZnsCampaignDelivery::STATUS_FAILED);
    });

    it('records two audit entries on a fail → retry → send flow', function (): void {
        [$campaign, $delivery] = makeZnsDeliveryFixture();

        $delivery->markFailure(
            message: 'Transient error',
            providerStatusCode: 503,
            providerResponse: null,
            nextRetryAt: now()->addMinutes(5),
        );

        $delivery->refresh();

        $delivery->markSent(
            providerMessageId: 'msg_retry_success',
            providerStatusCode: 200,
            providerResponse: null,
        );

        $delivery->refresh();

        expect($delivery->status)->toBe(ZnsCampaignDelivery::STATUS_SENT);

        $auditCount = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->count();

        expect($auditCount)->toBeGreaterThanOrEqual(2);

        $lastLog = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where('entity_id', $campaign->id)
            ->orderByDesc('id')
            ->first();

        expect($lastLog->metadata['trigger'])->toBe('zns_sent');
    });
});
