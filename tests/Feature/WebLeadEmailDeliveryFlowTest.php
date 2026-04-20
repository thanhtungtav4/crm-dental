<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Services\RuntimeMailerFactory;
use App\Services\WebLeadInternalEmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeWebLeadDeliveryFixture(array $deliveryOverrides = []): WebLeadEmailDelivery
{
    $branch = Branch::factory()->create();
    $ingestion = WebLeadIngestion::factory()->create([
        'branch_id' => $branch->id,
        'branch_code' => $branch->code,
        'status' => WebLeadIngestion::STATUS_CREATED,
        'payload' => ['full_name' => 'Nguyễn Văn A'],
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return WebLeadEmailDelivery::factory()->create(array_merge([
        'web_lead_ingestion_id' => $ingestion->id,
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_QUEUED,
        'recipient_email' => 'ops@example.test',
        'recipient_name' => 'OPS Team',
        'attempt_count' => 0,
    ], $deliveryOverrides));
}

function configureWebLeadRuntimeForFlowTest(): void
{
    $defaults = [
        'web_lead.internal_email_enabled' => true,
        'web_lead.internal_email_recipient_roles' => ['CSKH'],
        'web_lead.internal_email_recipient_emails' => "lead-box@example.test\nops@example.test",
        'web_lead.internal_email_subject_prefix' => '[CRM Lead]',
        'web_lead.internal_email_queue' => 'web-lead-mail',
        'web_lead.internal_email_max_attempts' => 5,
        'web_lead.internal_email_retry_delay_minutes' => 10,
        'web_lead.internal_email_smtp_host' => 'smtp.example.test',
        'web_lead.internal_email_smtp_port' => 587,
        'web_lead.internal_email_smtp_username' => 'lead-bot@example.test',
        'web_lead.internal_email_smtp_password' => 'secret',
        'web_lead.internal_email_smtp_scheme' => 'tls',
        'web_lead.internal_email_smtp_timeout_seconds' => 10,
        'web_lead.internal_email_from_address' => 'lead-bot@example.test',
        'web_lead.internal_email_from_name' => 'CRM Lead Bot',
    ];
    foreach ($defaults as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => 'web_lead',
            'value_type' => match ($key) {
                'web_lead.internal_email_enabled' => 'boolean',
                'web_lead.internal_email_recipient_roles' => 'json',
                'web_lead.internal_email_max_attempts',
                'web_lead.internal_email_retry_delay_minutes',
                'web_lead.internal_email_smtp_port',
                'web_lead.internal_email_smtp_timeout_seconds' => 'integer',
                default => 'text',
            },
            'is_secret' => str_ends_with($key, 'smtp_password'),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Claim (markProcessing) boundary
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — claim boundary', function (): void {
    it('transitions delivery from queued to processing via markProcessing boundary', function (): void {
        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_QUEUED]);

        $processing = $delivery->markProcessing('token-001');

        expect($processing)->toBeInstanceOf(WebLeadEmailDelivery::class)
            ->and($processing->status)->toBe(WebLeadEmailDelivery::STATUS_PROCESSING)
            ->and($processing->processing_token)->toBe('token-001')
            ->and((int) $processing->attempt_count)->toBe(1)
            ->and($processing->locked_at)->not->toBeNull();
    });

    it('blocks raw status write outside managed workflow', function (): void {
        $delivery = makeWebLeadDeliveryFixture();

        expect(fn () => $delivery->update(['status' => WebLeadEmailDelivery::STATUS_SENT]))
            ->toThrow(ValidationException::class);

        expect($delivery->fresh()?->status)->toBe(WebLeadEmailDelivery::STATUS_QUEUED);
    });

    it('blocks invalid status transitions', function (): void {
        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_SENT]);

        // Cannot move from sent directly to processing
        expect(fn () => $delivery->markProcessing('bad-token'))
            ->toThrow(ValidationException::class);
    });
});

// ---------------------------------------------------------------------------
// Send (markSent) boundary
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — send boundary', function (): void {
    it('transitions delivery from processing to sent via markSent boundary', function (): void {
        $delivery = makeWebLeadDeliveryFixture();
        $delivery->markProcessing('wf-send-001');

        $sent = $delivery->markSent('smtp-msg-001');

        expect($sent->status)->toBe(WebLeadEmailDelivery::STATUS_SENT)
            ->and($sent->transport_message_id)->toBe('smtp-msg-001')
            ->and($sent->sent_at)->not->toBeNull()
            ->and($sent->processing_token)->toBeNull()
            ->and($sent->locked_at)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Fail (markFailure) boundary — retryable + terminal
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — fail boundary', function (): void {
    it('transitions delivery to retryable on transient failure', function (): void {
        $delivery = makeWebLeadDeliveryFixture();
        $delivery->markProcessing('wf-fail-001');

        $retryable = $delivery->markFailure(
            message: 'SMTP temporarily unavailable',
            terminal: false,
            delaySeconds: 300,
        );

        expect($retryable->status)->toBe(WebLeadEmailDelivery::STATUS_RETRYABLE)
            ->and($retryable->next_retry_at)->not->toBeNull()
            ->and($retryable->last_error_message)->toContain('SMTP temporarily unavailable');
    });

    it('transitions delivery to dead on terminal failure', function (): void {
        $delivery = makeWebLeadDeliveryFixture();
        $delivery->markProcessing('wf-fail-002');

        $dead = $delivery->markFailure(
            message: 'Missing web lead internal email SMTP host.',
            terminal: true,
            delaySeconds: 0,
        );

        expect($dead->status)->toBe(WebLeadEmailDelivery::STATUS_DEAD)
            ->and($dead->next_retry_at)->toBeNull()
            ->and($dead->last_error_message)->toContain('SMTP host');
    });
});

// ---------------------------------------------------------------------------
// Reset / replay boundary
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — resetForReplay boundary', function (): void {
    it('resets a dead delivery back to queued for replay', function (): void {
        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_DEAD]);

        $replayed = $delivery->resetForReplay(['manual_resend_count' => 1]);

        expect($replayed->status)->toBe(WebLeadEmailDelivery::STATUS_QUEUED)
            ->and($replayed->processing_token)->toBeNull()
            ->and($replayed->transport_message_id)->toBeNull()
            ->and($replayed->sent_at)->toBeNull()
            ->and((int) $replayed->manual_resend_count)->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// Full flow: claim → send → audit record (via processDelivery)
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — claim → send flow (processDelivery)', function (): void {
    it('marks delivery sent and writes structured audit on successful send', function (): void {
        configureWebLeadRuntimeForFlowTest();

        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_QUEUED]);

        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')->andReturnSelf();
        $mailer->shouldReceive('send')->andReturn(null);

        $factory = \Mockery::mock(RuntimeMailerFactory::class);
        $factory->shouldReceive('webLeadInternalMailer')->andReturn($mailer);
        app()->instance(RuntimeMailerFactory::class, $factory);

        $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery((int) $delivery->id);

        expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_SENT)
            ->and($delivery->fresh()?->status)->toBe(WebLeadEmailDelivery::STATUS_SENT);

        $audit = AuditLog::query()
            ->where('entity_type', 'web_lead_email_delivery')
            ->where('entity_id', $delivery->id)
            ->where('action', AuditLog::ACTION_COMPLETE)
            ->latest('id')
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit?->metadata)->toMatchArray([
                'channel' => 'web_lead_internal_email',
                'trigger' => 'send_success',
                'status_from' => WebLeadEmailDelivery::STATUS_PROCESSING,
                'status_to' => WebLeadEmailDelivery::STATUS_SENT,
            ]);
    });
});

// ---------------------------------------------------------------------------
// Full flow: claim → fail retryable → audit record (via processDelivery)
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — claim → fail (retryable) flow', function (): void {
    it('marks delivery retryable and writes structured audit on transient failure', function (): void {
        configureWebLeadRuntimeForFlowTest();

        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_QUEUED]);

        $factory = \Mockery::mock(RuntimeMailerFactory::class);
        $factory->shouldReceive('webLeadInternalMailer')
            ->andThrow(new RuntimeException('SMTP connection timeout'));
        app()->instance(RuntimeMailerFactory::class, $factory);

        $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery((int) $delivery->id);

        expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_RETRYABLE)
            ->and($delivery->fresh()?->status)->toBe(WebLeadEmailDelivery::STATUS_RETRYABLE);

        $audit = AuditLog::query()
            ->where('entity_type', 'web_lead_email_delivery')
            ->where('entity_id', $delivery->id)
            ->where('action', AuditLog::ACTION_FAIL)
            ->latest('id')
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit?->metadata)->toMatchArray([
                'channel' => 'web_lead_internal_email',
                'trigger' => 'send_retryable',
                'status_from' => WebLeadEmailDelivery::STATUS_PROCESSING,
                'status_to' => WebLeadEmailDelivery::STATUS_RETRYABLE,
            ]);
    });
});

// ---------------------------------------------------------------------------
// Full flow: claim → fail terminal → audit record (via processDelivery)
// ---------------------------------------------------------------------------

describe('WebLeadEmailDelivery — claim → fail (terminal / dead) flow', function (): void {
    it('marks delivery dead and writes structured audit on terminal failure', function (): void {
        configureWebLeadRuntimeForFlowTest();

        $delivery = makeWebLeadDeliveryFixture(['status' => WebLeadEmailDelivery::STATUS_QUEUED]);

        $factory = \Mockery::mock(RuntimeMailerFactory::class);
        $factory->shouldReceive('webLeadInternalMailer')
            ->andThrow(new RuntimeException('Missing web lead internal email SMTP host.'));
        app()->instance(RuntimeMailerFactory::class, $factory);

        $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery((int) $delivery->id);

        expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_DEAD)
            ->and($delivery->fresh()?->status)->toBe(WebLeadEmailDelivery::STATUS_DEAD);

        $audit = AuditLog::query()
            ->where('entity_type', 'web_lead_email_delivery')
            ->where('entity_id', $delivery->id)
            ->where('action', AuditLog::ACTION_FAIL)
            ->latest('id')
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit?->metadata)->toMatchArray([
                'channel' => 'web_lead_internal_email',
                'trigger' => 'send_dead',
                'status_from' => WebLeadEmailDelivery::STATUS_PROCESSING,
                'status_to' => WebLeadEmailDelivery::STATUS_DEAD,
            ]);
    });
});
