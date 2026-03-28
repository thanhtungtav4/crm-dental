<?php

use App\Jobs\SendWebLeadInternalEmailDelivery;
use App\Mail\WebLeadInternalNotificationMail;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Services\RuntimeMailerFactory;
use App\Services\WebLeadInternalEmailNotificationService;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function configureInternalEmailFeatureWebLeadApi(
    bool $enabled,
    string $token,
    ?string $defaultBranchCode = null,
    int $rateLimit = 60,
): void {
    ClinicSetting::setValue('web_lead.enabled', $enabled, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('web_lead.api_token', $token, [
        'group' => 'web_lead',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('web_lead.default_branch_code', $defaultBranchCode ?? '', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('web_lead.rate_limit_per_minute', $rateLimit, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);
}

function configureInternalEmailFeatureWebLeadRuntime(array $overrides = []): void
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

    foreach (array_replace($defaults, $overrides) as $key => $value) {
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

it('queues one delivery per resolved recipient when a brand new web lead is created', function (): void {
    Queue::fake();

    $branch = Branch::factory()->create([
        'code' => 'BR-WEB-EMAIL',
        'active' => true,
    ]);

    configureInternalEmailFeatureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $branch->code,
        rateLimit: 120,
    );
    configureInternalEmailFeatureWebLeadRuntime();

    $sameBranchRecipient = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => 'same-branch@example.test',
    ]);
    $sameBranchRecipient->assignRole('CSKH');

    $otherBranchRecipient = User::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
        'email' => 'other-branch@example.test',
    ]);
    $otherBranchRecipient->assignRole('CSKH');

    $requestId = (string) Str::uuid();

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Mail Queue Lead',
        'phone' => '0905566778',
        'branch_code' => $branch->code,
        'note' => 'Đặt lịch tư vấn implant',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ])->assertCreated();

    $deliveries = WebLeadEmailDelivery::query()
        ->orderBy('id')
        ->get();

    expect($deliveries)->toHaveCount(3)
        ->and($deliveries->pluck('recipient_email')->all())->toBe([
            'same-branch@example.test',
            'lead-box@example.test',
            'ops@example.test',
        ])
        ->and($deliveries->every(fn (WebLeadEmailDelivery $delivery): bool => $delivery->status === WebLeadEmailDelivery::STATUS_QUEUED))
        ->toBeTrue();

    Queue::assertPushed(SendWebLeadInternalEmailDelivery::class, 3);
    Queue::assertPushed(SendWebLeadInternalEmailDelivery::class, function (SendWebLeadInternalEmailDelivery $job): bool {
        return $job->queue === 'web-lead-mail';
    });
});

it('does not create internal email deliveries when web lead is merged into an existing customer', function (): void {
    Queue::fake();

    configureInternalEmailFeatureWebLeadApi(enabled: true, token: 'web-token');
    configureInternalEmailFeatureWebLeadRuntime();

    Customer::factory()->create([
        'phone' => '0909 888 777',
        'status' => 'lead',
    ]);

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Existing Lead',
        'phone' => '+84 909 888 777',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ])->assertOk()
        ->assertJsonPath('data.created', false);

    expect(WebLeadEmailDelivery::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('marks a queued delivery as sent when the mail transport succeeds', function (): void {
    $delivery = WebLeadEmailDelivery::factory()->create();
    $sentMessage = new class
    {
        public function getMessageId(): string
        {
            return 'smtp-message-001';
        }
    };

    $mailer = \Mockery::mock(Mailer::class);
    $mailer->shouldReceive('to')->once()->with($delivery->recipient_email, $delivery->recipient_name)->andReturnSelf();
    $mailer->shouldReceive('send')
        ->once()
        ->with(\Mockery::on(fn ($mail): bool => $mail instanceof WebLeadInternalNotificationMail))
        ->andReturn($sentMessage);

    $factory = \Mockery::mock(RuntimeMailerFactory::class);
    $factory->shouldReceive('webLeadInternalMailer')->once()->andReturn($mailer);
    app()->instance(RuntimeMailerFactory::class, $factory);

    $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery($delivery->id);

    expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_SENT)
        ->and($delivery->fresh()->status)->toBe(WebLeadEmailDelivery::STATUS_SENT)
        ->and($delivery->fresh()->sent_at)->not->toBeNull()
        ->and($delivery->fresh()->transport_message_id)->toBe('smtp-message-001');
});

it('marks a queued delivery as retryable for transient mail errors', function (): void {
    $delivery = WebLeadEmailDelivery::factory()->create();

    $mailer = \Mockery::mock(Mailer::class);
    $mailer->shouldReceive('to')->once()->with($delivery->recipient_email, $delivery->recipient_name)->andReturnSelf();
    $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP temporarily unavailable'));

    $factory = \Mockery::mock(RuntimeMailerFactory::class);
    $factory->shouldReceive('webLeadInternalMailer')->once()->andReturn($mailer);
    app()->instance(RuntimeMailerFactory::class, $factory);

    $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery($delivery->id);

    expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_RETRYABLE)
        ->and($result['delay_seconds'])->toBe(600)
        ->and($delivery->fresh()->status)->toBe(WebLeadEmailDelivery::STATUS_RETRYABLE)
        ->and($delivery->fresh()->next_retry_at)->not->toBeNull();
});

it('marks a queued delivery as dead for invalid runtime mailer configuration', function (): void {
    $delivery = WebLeadEmailDelivery::factory()->create();

    $factory = \Mockery::mock(RuntimeMailerFactory::class);
    $factory->shouldReceive('webLeadInternalMailer')
        ->once()
        ->andThrow(new RuntimeException('Missing web lead internal email SMTP host.'));
    app()->instance(RuntimeMailerFactory::class, $factory);

    $result = app(WebLeadInternalEmailNotificationService::class)->processDelivery($delivery->id);

    expect($result['status'])->toBe(WebLeadEmailDelivery::STATUS_DEAD)
        ->and($delivery->fresh()->status)->toBe(WebLeadEmailDelivery::STATUS_DEAD)
        ->and($delivery->fresh()->last_error_message)->toContain('SMTP host');
});

it('blocks raw status mutation outside the web lead email workflow contract', function (): void {
    $delivery = WebLeadEmailDelivery::factory()->create();

    expect(function () use ($delivery): void {
        $delivery->update([
            'status' => WebLeadEmailDelivery::STATUS_SENT,
        ]);
    })->toThrow(ValidationException::class);
});

it('requeues a terminal delivery through the managed resend workflow', function (): void {
    Queue::fake();

    configureInternalEmailFeatureWebLeadRuntime();

    $delivery = WebLeadEmailDelivery::factory()->create([
        'status' => WebLeadEmailDelivery::STATUS_DEAD,
        'attempt_count' => 3,
        'manual_resend_count' => 0,
        'processing_token' => 'stale-token',
        'locked_at' => now()->subMinutes(30),
        'next_retry_at' => now()->subMinutes(5),
        'sent_at' => now()->subMinutes(15),
        'transport_message_id' => 'smtp-old-message',
        'last_error_message' => 'Previous SMTP failure',
    ]);

    $requeuedDelivery = app(WebLeadInternalEmailNotificationService::class)->resend($delivery);

    expect($requeuedDelivery)->not->toBeNull()
        ->and($requeuedDelivery->status)->toBe(WebLeadEmailDelivery::STATUS_QUEUED)
        ->and((int) $requeuedDelivery->attempt_count)->toBe(3)
        ->and((int) $requeuedDelivery->manual_resend_count)->toBe(1)
        ->and($requeuedDelivery->processing_token)->toBeNull()
        ->and($requeuedDelivery->locked_at)->toBeNull()
        ->and($requeuedDelivery->next_retry_at)->toBeNull()
        ->and($requeuedDelivery->sent_at)->toBeNull()
        ->and($requeuedDelivery->transport_message_id)->toBeNull()
        ->and($requeuedDelivery->last_error_message)->toBeNull();

    Queue::assertPushed(SendWebLeadInternalEmailDelivery::class, 1);
});
