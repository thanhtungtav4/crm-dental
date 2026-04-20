<?php

namespace App\Services;

use App\Filament\Pages\FrontdeskControlCenter;
use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendWebLeadInternalEmailDelivery;
use App\Mail\WebLeadInternalNotificationMail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebLeadInternalEmailNotificationService
{
    public function __construct(
        protected RuntimeMailerFactory $runtimeMailerFactory,
    ) {}

    public function queueForNewLead(WebLeadIngestion $ingestion, Customer $customer): void
    {
        if (! ClinicRuntimeSettings::webLeadInternalEmailEnabled()) {
            return;
        }

        $recipients = $this->resolveRecipients($customer);

        if ($recipients->isEmpty()) {
            AuditLog::record(
                entityType: 'web_lead_email_delivery',
                entityId: (int) $ingestion->id,
                action: AuditLog::ACTION_FAIL,
                metadata: [
                    'channel' => 'web_lead_internal_email',
                    'reason' => 'no_recipients',
                    'customer_id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                ],
                branchId: $customer->branch_id ? (int) $customer->branch_id : null,
            );

            return;
        }

        foreach ($recipients as $recipient) {
            $delivery = WebLeadEmailDelivery::query()->firstOrCreate(
                ['dedupe_key' => $this->dedupeKey($ingestion, $recipient)],
                [
                    'web_lead_ingestion_id' => $ingestion->id,
                    'customer_id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                    'recipient_user_id' => $recipient['recipient_user_id'],
                    'recipient_type' => $recipient['recipient_type'],
                    'recipient_email' => $recipient['recipient_email'],
                    'recipient_name' => $recipient['recipient_name'],
                    'status' => WebLeadEmailDelivery::STATUS_QUEUED,
                    'payload' => $this->payloadSnapshot($ingestion, $customer),
                    'mailer_snapshot' => $this->mailerSnapshot(),
                ],
            );

            if (! $delivery->wasRecentlyCreated) {
                continue;
            }

            $this->dispatchDelivery($delivery);

            AuditLog::record(
                entityType: 'web_lead_email_delivery',
                entityId: $delivery->id,
                action: AuditLog::ACTION_CREATE,
                metadata: [
                    'channel' => 'web_lead_internal_email',
                    'customer_id' => $customer->id,
                    'web_lead_ingestion_id' => $ingestion->id,
                    'recipient_type' => $delivery->recipient_type,
                    'recipient_user_id' => $delivery->recipient_user_id,
                ],
                branchId: $customer->branch_id ? (int) $customer->branch_id : null,
            );
        }
    }

    public function resend(WebLeadEmailDelivery $delivery, ?int $actorId = null): WebLeadEmailDelivery
    {
        if (! ClinicRuntimeSettings::webLeadInternalEmailEnabled()) {
            throw ValidationException::withMessages([
                'delivery' => 'Web lead internal email đang tắt ở runtime settings.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $actorId): WebLeadEmailDelivery {
            /** @var WebLeadEmailDelivery $lockedDelivery */
            $lockedDelivery = WebLeadEmailDelivery::query()
                ->with(['webLeadIngestion', 'customer'])
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            $ingestion = $lockedDelivery->webLeadIngestion;
            $customer = $lockedDelivery->customer;

            if (! $ingestion instanceof WebLeadIngestion || ! $customer instanceof Customer) {
                throw ValidationException::withMessages([
                    'delivery' => 'Không còn đủ dữ liệu web lead để gửi lại email.',
                ]);
            }

            $lockedDelivery->resetForReplay([
                'payload' => $this->payloadSnapshot($ingestion, $customer),
                'mailer_snapshot' => $this->mailerSnapshot(),
                'manual_resend_count' => (int) $lockedDelivery->manual_resend_count + 1,
            ]);

            $this->dispatchDelivery($lockedDelivery);

            AuditLog::record(
                entityType: 'web_lead_email_delivery',
                entityId: $lockedDelivery->id,
                action: AuditLog::ACTION_RUN,
                actorId: $actorId,
                metadata: [
                    'channel' => 'web_lead_internal_email',
                    'trigger' => 'manual_resend',
                    'manual_resend_count' => $lockedDelivery->manual_resend_count,
                ],
                branchId: $lockedDelivery->branch_id ? (int) $lockedDelivery->branch_id : null,
            );

            return $lockedDelivery->fresh([
                'webLeadIngestion',
                'customer',
                'branch',
                'recipientUser',
            ]);
        });
    }

    /**
     * @return array{status:string,delay_seconds:int}
     */
    public function processDelivery(int $deliveryId): array
    {
        $claimedDelivery = $this->claimDelivery($deliveryId);

        if (! $claimedDelivery instanceof WebLeadEmailDelivery) {
            return [
                'status' => WebLeadEmailDelivery::STATUS_SKIPPED,
                'delay_seconds' => 0,
            ];
        }

        try {
            $mailer = $this->runtimeMailerFactory->webLeadInternalMailer();
            $sentMessage = $mailer
                ->to($claimedDelivery->recipient_email, $claimedDelivery->recipient_name)
                ->send(new WebLeadInternalNotificationMail($claimedDelivery));
            $transportMessageId = is_object($sentMessage) && method_exists($sentMessage, 'getMessageId')
                ? $sentMessage->getMessageId()
                : null;

            $claimedDelivery->markSent($transportMessageId);

            AuditLog::record(
                entityType: 'web_lead_email_delivery',
                entityId: $claimedDelivery->id,
                action: AuditLog::ACTION_COMPLETE,
                metadata: [
                    'channel' => 'web_lead_internal_email',
                    'trigger' => 'send_success',
                    'status_from' => WebLeadEmailDelivery::STATUS_PROCESSING,
                    'status_to' => WebLeadEmailDelivery::STATUS_SENT,
                    'attempt_count' => $claimedDelivery->attempt_count,
                    'recipient_email' => $claimedDelivery->recipient_email,
                    'transport_message_id' => $transportMessageId,
                ],
                branchId: $claimedDelivery->branch_id ? (int) $claimedDelivery->branch_id : null,
            );

            return [
                'status' => WebLeadEmailDelivery::STATUS_SENT,
                'delay_seconds' => 0,
            ];
        } catch (Throwable $throwable) {
            return $this->markDeliveryFailure($claimedDelivery, $throwable);
        }
    }

    /**
     * @return Collection<int, array{
     *     recipient_type:string,
     *     recipient_user_id:?int,
     *     recipient_email:string,
     *     recipient_name:?string
     * }>
     */
    protected function resolveRecipients(Customer $customer): Collection
    {
        $targets = collect();
        $branchId = $customer->branch_id !== null ? (int) $customer->branch_id : null;

        $roleRecipients = User::query()
            ->role(ClinicRuntimeSettings::webLeadInternalEmailRecipientRoles())
            ->whereNotNull('email')
            ->select('users.*')
            ->distinct()
            ->get()
            ->filter(function (User $user) use ($branchId): bool {
                if ($branchId === null) {
                    return $user->hasRole('Admin');
                }

                return $user->canAccessBranch($branchId);
            });

        foreach ($roleRecipients as $recipient) {
            $email = trim((string) $recipient->email);

            if ($email === '') {
                continue;
            }

            $targets->put(Str::lower($email), [
                'recipient_type' => WebLeadEmailDelivery::RECIPIENT_TYPE_USER,
                'recipient_user_id' => (int) $recipient->id,
                'recipient_email' => $email,
                'recipient_name' => $recipient->name,
            ]);
        }

        foreach (ClinicRuntimeSettings::webLeadInternalEmailRecipientEmails() as $recipientEmail) {
            $normalizedEmail = Str::lower($recipientEmail);

            if ($targets->has($normalizedEmail)) {
                continue;
            }

            $targets->put($normalizedEmail, [
                'recipient_type' => WebLeadEmailDelivery::RECIPIENT_TYPE_MAILBOX,
                'recipient_user_id' => null,
                'recipient_email' => $recipientEmail,
                'recipient_name' => null,
            ]);
        }

        return $targets->values();
    }

    protected function dispatchDelivery(WebLeadEmailDelivery $delivery): void
    {
        SendWebLeadInternalEmailDelivery::dispatch((int) $delivery->id)
            ->onQueue(ClinicRuntimeSettings::webLeadInternalEmailQueue())
            ->afterCommit();
    }

    protected function claimDelivery(int $deliveryId): ?WebLeadEmailDelivery
    {
        return DB::transaction(function () use ($deliveryId): ?WebLeadEmailDelivery {
            /** @var WebLeadEmailDelivery|null $delivery */
            $delivery = WebLeadEmailDelivery::query()
                ->with(['webLeadIngestion.customer.branch', 'customer.branch', 'branch', 'recipientUser'])
                ->lockForUpdate()
                ->find($deliveryId);

            if (! $delivery instanceof WebLeadEmailDelivery) {
                return null;
            }

            if (
                $delivery->status === WebLeadEmailDelivery::STATUS_PROCESSING
                && $delivery->locked_at !== null
                && $delivery->locked_at->gt(now()->subMinutes($this->lockTtlMinutes()))
            ) {
                return null;
            }

            if ($delivery->status === WebLeadEmailDelivery::STATUS_RETRYABLE && $delivery->next_retry_at?->isFuture()) {
                return null;
            }

            if (in_array($delivery->status, [WebLeadEmailDelivery::STATUS_SENT, WebLeadEmailDelivery::STATUS_SKIPPED], true)) {
                return null;
            }

            $delivery->markProcessing((string) Str::uuid());

            return $delivery;
        }, 3);
    }

    /**
     * @return array{status:string,delay_seconds:int}
     */
    protected function markDeliveryFailure(WebLeadEmailDelivery $delivery, Throwable $throwable): array
    {
        $isRetryable = $this->isRetryableThrowable($throwable);
        $maxAttempts = ClinicRuntimeSettings::webLeadInternalEmailMaxAttempts();
        $delaySeconds = ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes() * 60;
        $terminal = ! $isRetryable || (int) $delivery->attempt_count >= $maxAttempts;

        $delivery->markFailure(
            message: Str::limit($throwable->getMessage(), 2000, ''),
            terminal: $terminal,
            delaySeconds: $delaySeconds,
        );

        AuditLog::record(
            entityType: 'web_lead_email_delivery',
            entityId: $delivery->id,
            action: AuditLog::ACTION_FAIL,
            metadata: [
                'channel' => 'web_lead_internal_email',
                'trigger' => $terminal ? 'send_dead' : 'send_retryable',
                'status_from' => WebLeadEmailDelivery::STATUS_PROCESSING,
                'status_to' => $delivery->status,
                'attempt_count' => $delivery->attempt_count,
                'message' => Str::limit($throwable->getMessage(), 500, ''),
            ],
            branchId: $delivery->branch_id ? (int) $delivery->branch_id : null,
        );

        return [
            'status' => $delivery->status,
            'delay_seconds' => $terminal ? 0 : $delaySeconds,
        ];
    }

    /**
     * @param  array{
     *     recipient_type:string,
     *     recipient_user_id:?int,
     *     recipient_email:string,
     *     recipient_name:?string
     * }  $recipient
     */
    protected function dedupeKey(WebLeadIngestion $ingestion, array $recipient): string
    {
        return hash(
            'sha256',
            implode('|', [
                'web-lead-internal-email',
                (string) $ingestion->id,
                (string) $recipient['recipient_type'],
                (string) ($recipient['recipient_user_id'] ?? ''),
                Str::lower((string) $recipient['recipient_email']),
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadSnapshot(WebLeadIngestion $ingestion, Customer $customer): array
    {
        $subjectPrefix = trim(ClinicRuntimeSettings::webLeadInternalEmailSubjectPrefix());
        $branchLabel = $customer->branch?->name ?? 'Chưa xác định chi nhánh';

        return [
            'subject' => trim(implode(' | ', array_filter([
                $subjectPrefix !== '' ? $subjectPrefix : null,
                'Lead mới từ website',
                $branchLabel,
                $customer->full_name ?: null,
            ]))),
            'customer_name' => $customer->full_name,
            'customer_phone' => $customer->phone,
            'branch_name' => $branchLabel,
            'request_id' => $ingestion->request_id,
            'note' => data_get($ingestion->payload, 'note'),
            'ingestion_status' => $ingestion->status,
            'customer_url' => url(CustomerResource::getUrl('edit', ['record' => $customer])),
            'frontdesk_url' => url(FrontdeskControlCenter::getUrl()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mailerSnapshot(): array
    {
        return [
            'host' => ClinicRuntimeSettings::webLeadInternalEmailSmtpHost(),
            'port' => ClinicRuntimeSettings::webLeadInternalEmailSmtpPort(),
            'scheme' => ClinicRuntimeSettings::webLeadInternalEmailSmtpScheme(),
            'from_address' => ClinicRuntimeSettings::webLeadInternalEmailFromAddress(),
            'from_name' => ClinicRuntimeSettings::webLeadInternalEmailFromName(),
            'queue' => ClinicRuntimeSettings::webLeadInternalEmailQueue(),
            'max_attempts' => ClinicRuntimeSettings::webLeadInternalEmailMaxAttempts(),
            'retry_delay_minutes' => ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes(),
            'timeout_seconds' => ClinicRuntimeSettings::webLeadInternalEmailTimeoutSeconds(),
        ];
    }

    protected function lockTtlMinutes(): int
    {
        return max(5, ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes());
    }

    protected function isRetryableThrowable(Throwable $throwable): bool
    {
        $message = Str::lower($throwable->getMessage());

        return ! str_contains($message, 'missing web lead internal email smtp')
            && ! str_contains($message, 'from address')
            && ! str_contains($message, 'recipient');
    }
}
