<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ZnsCampaignRunnerService
{
    protected const DELIVERY_CHUNK_SIZE = 200;

    protected const DELIVERY_RETRY_DELAY_MINUTES = 15;

    protected const DELIVERY_LOCK_TTL_MINUTES = 15;

    public function __construct(
        private readonly ZnsProviderClient $providerClient,
        private readonly ZnsPayloadSanitizer $payloadSanitizer,
        private readonly ZnsCampaignWorkflowService $workflowService,
    ) {}

    /**
     * @return array{processed:int,sent:int,failed:int,skipped:int}
     */
    public function runCampaign(ZnsCampaign $campaign): array
    {
        $campaign->refresh();
        $this->assertZnsReadyForRun();

        $runnableStatuses = [
            ZnsCampaign::STATUS_DRAFT,
            ZnsCampaign::STATUS_SCHEDULED,
            ZnsCampaign::STATUS_RUNNING,
            ZnsCampaign::STATUS_FAILED,
        ];

        if (! in_array($campaign->status, $runnableStatuses, true)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        if (
            $campaign->status === ZnsCampaign::STATUS_SCHEDULED
            && $campaign->scheduled_at !== null
            && $campaign->scheduled_at->isFuture()
        ) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $campaign = $this->workflowService->markRunning($campaign);

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $maxDeliveryAttempts = ClinicRuntimeSettings::znsCampaignDeliveryMaxAttempts();

        try {
            $templateId = $this->resolveTemplateId($campaign);

            if ($templateId === '') {
                throw ValidationException::withMessages([
                    'template_id' => 'Campaign chưa có template_id hợp lệ để gửi ZNS.',
                ]);
            }

            $this->reclaimStaleProcessingDeliveries($campaign, $maxDeliveryAttempts);

            $skipped += $this->prepareDeliveriesForAudience(
                campaign: $campaign,
                templateId: $templateId,
                maxDeliveryAttempts: $maxDeliveryAttempts,
            );

            while (true) {
                $claim = $this->claimNextDelivery($campaign, $maxDeliveryAttempts);

                if ($claim === null) {
                    break;
                }

                $processed++;

                $deliveryResult = $this->processClaimedDelivery(
                    campaign: $campaign,
                    claim: $claim,
                    maxDeliveryAttempts: $maxDeliveryAttempts,
                );

                if ($deliveryResult === ZnsCampaignDelivery::STATUS_SENT) {
                    $sent++;

                    continue;
                }

                if ($deliveryResult === ZnsCampaignDelivery::STATUS_FAILED) {
                    $failed++;

                    continue;
                }

                $skipped++;
            }

            $this->refreshCampaignSummary($campaign);
        } catch (ValidationException $exception) {
            $this->workflowService->markFailed(
                campaign: $campaign,
                reason: $exception->getMessage(),
            );

            throw $exception;
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    protected function prepareDeliveriesForAudience(ZnsCampaign $campaign, string $templateId, int $maxDeliveryAttempts): int
    {
        $skipped = 0;

        $this->audienceQuery($campaign)->chunkById(
            self::DELIVERY_CHUNK_SIZE,
            function (Collection $patients) use ($campaign, $templateId, $maxDeliveryAttempts, &$skipped): void {
                foreach ($patients as $patient) {
                    $rawPhone = trim((string) ($patient->phone ?? ''));
                    $normalizedPhone = $this->normalizePhone($rawPhone);
                    $idempotencyKey = $this->deliveryIdempotencyKey(
                        campaignId: (int) $campaign->id,
                        normalizedPhone: $normalizedPhone,
                        templateId: $templateId,
                    );

                    $delivery = ZnsCampaignDelivery::query()->firstOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'zns_campaign_id' => $campaign->id,
                            'patient_id' => (int) $patient->id,
                            'customer_id' => $patient->customer_id ? (int) $patient->customer_id : null,
                            'branch_id' => $campaign->branch_id,
                            'phone' => $rawPhone,
                            'normalized_phone' => $normalizedPhone,
                            'status' => ZnsCampaignDelivery::STATUS_QUEUED,
                            'processing_token' => null,
                            'locked_at' => null,
                            'attempt_count' => 0,
                            'payload' => [
                                'template_key' => $campaign->template_key,
                                'template_id' => $templateId,
                                'message' => $campaign->message_payload,
                            ],
                            'template_key_snapshot' => $campaign->template_key,
                            'template_id_snapshot' => $templateId,
                        ],
                    );

                    if ($delivery->wasRecentlyCreated) {
                        continue;
                    }

                    if (in_array($delivery->status, [ZnsCampaignDelivery::STATUS_SENT, ZnsCampaignDelivery::STATUS_SKIPPED], true)) {
                        $skipped++;

                        continue;
                    }

                    if ($delivery->processing_token !== null && ! $this->isDeliveryLockExpired($delivery)) {
                        $skipped++;

                        continue;
                    }

                    if (
                        $delivery->status === ZnsCampaignDelivery::STATUS_FAILED
                        && $this->shouldSkipFailedDelivery($delivery, $maxDeliveryAttempts)
                    ) {
                        $skipped++;

                        continue;
                    }

                    $delivery->forceFill([
                        'zns_campaign_id' => $campaign->id,
                        'patient_id' => (int) $patient->id,
                        'customer_id' => $patient->customer_id ? (int) $patient->customer_id : null,
                        'branch_id' => $campaign->branch_id,
                        'phone' => $rawPhone,
                        'normalized_phone' => $normalizedPhone,
                        'payload' => [
                            'template_key' => $campaign->template_key,
                            'template_id' => $templateId,
                            'message' => $campaign->message_payload,
                        ],
                        'template_key_snapshot' => $campaign->template_key,
                        'template_id_snapshot' => $templateId,
                    ])->save();
                }
            },
            column: 'patients.id',
            alias: 'id',
        );

        return $skipped;
    }

    protected function audienceQuery(ZnsCampaign $campaign): Builder
    {
        $latestAppointmentSubquery = Appointment::query()
            ->selectRaw('patient_id, MAX(date) as latest_appointment_date')
            ->groupBy('patient_id');

        return Patient::query()
            ->from('patients')
            ->leftJoinSub($latestAppointmentSubquery, 'latest_appointments', function (JoinClause $join): void {
                $join->on('latest_appointments.patient_id', '=', 'patients.id');
            })
            ->leftJoin('customers', 'customers.id', '=', 'patients.customer_id')
            ->select([
                'patients.id',
                'patients.customer_id',
                'patients.phone',
                'patients.first_branch_id',
                'latest_appointments.latest_appointment_date',
            ])
            ->when($campaign->branch_id, fn (Builder $query) => $query->where('patients.first_branch_id', (int) $campaign->branch_id))
            ->when($campaign->audience_source, fn (Builder $query) => $query->where('customers.source', (string) $campaign->audience_source))
            ->when($campaign->audience_last_visit_before_days, function (Builder $query) use ($campaign): void {
                $cutoff = now()->subDays((int) $campaign->audience_last_visit_before_days);
                $query->where(function (Builder $innerQuery) use ($cutoff): void {
                    $innerQuery->whereNull('latest_appointments.latest_appointment_date')
                        ->orWhere('latest_appointments.latest_appointment_date', '<=', $cutoff);
                });
            })
            ->orderBy('patients.id');
    }

    /**
     * @param  array{delivery_id:int,processing_token:string,idempotency_key:string,phone:string,normalized_phone:string,template_id:string,attempt_count:int,payload:array<string,mixed>}  $claim
     */
    protected function processClaimedDelivery(ZnsCampaign $campaign, array $claim, int $maxDeliveryAttempts): string
    {
        $providerPayload = null;
        $retryable = true;
        $sendResult = [
            'success' => false,
            'status' => null,
            'provider_message_id' => null,
            'provider_status_code' => null,
            'error' => null,
            'response' => null,
        ];

        if ($claim['template_id'] === '') {
            $retryable = false;
            $sendResult['provider_status_code'] = 'validation_missing_template';
            $sendResult['error'] = 'Campaign chưa có template_id hợp lệ để gửi ZNS.';
        } elseif ($claim['normalized_phone'] === '') {
            $retryable = false;
            $sendResult['provider_status_code'] = 'validation_missing_phone';
            $sendResult['error'] = 'Thiếu số điện thoại nhận ZNS.';
        } else {
            $providerPayload = $this->buildProviderPayload(
                campaign: $campaign,
                templateId: $claim['template_id'],
                normalizedPhone: $claim['normalized_phone'],
                idempotencyKey: $claim['idempotency_key'],
                messagePayload: is_array(data_get($claim['payload'], 'message'))
                    ? data_get($claim['payload'], 'message')
                    : [],
            );

            $sendResult = $this->providerClient->sendTemplate($providerPayload);
            $retryable = $this->isRetryableProviderFailure($sendResult);
        }

        if (($sendResult['success'] ?? false) === true) {
            $persisted = $this->finalizeClaimedDelivery(
                deliveryId: $claim['delivery_id'],
                processingToken: $claim['processing_token'],
                providerPayload: $providerPayload,
                sendResult: $sendResult,
                status: ZnsCampaignDelivery::STATUS_SENT,
                nextRetryAt: null,
            );

            return $persisted ? ZnsCampaignDelivery::STATUS_SENT : ZnsCampaignDelivery::STATUS_SKIPPED;
        }

        $nextRetryAt = null;
        if ($retryable) {
            $deliveryAttempt = (int) ($claim['attempt_count'] ?? $maxDeliveryAttempts);

            if ($deliveryAttempt < $maxDeliveryAttempts) {
                $nextRetryAt = now()->addMinutes(self::DELIVERY_RETRY_DELAY_MINUTES);
            }
        }

        $persisted = $this->finalizeClaimedDelivery(
            deliveryId: $claim['delivery_id'],
            processingToken: $claim['processing_token'],
            providerPayload: $providerPayload,
            sendResult: $sendResult,
            status: ZnsCampaignDelivery::STATUS_FAILED,
            nextRetryAt: $nextRetryAt,
        );

        return $persisted ? ZnsCampaignDelivery::STATUS_FAILED : ZnsCampaignDelivery::STATUS_SKIPPED;
    }

    /**
     * @return array{delivery_id:int,processing_token:string,idempotency_key:string,phone:string,normalized_phone:string,template_id:string,attempt_count:int,payload:array<string,mixed>}|null
     */
    protected function claimNextDelivery(ZnsCampaign $campaign, int $maxDeliveryAttempts): ?array
    {
        return DB::transaction(function () use ($campaign, $maxDeliveryAttempts): ?array {
            $delivery = ZnsCampaignDelivery::query()
                ->where('zns_campaign_id', $campaign->id)
                ->whereNull('processing_token')
                ->where(function (Builder $statusQuery) use ($maxDeliveryAttempts): void {
                    $statusQuery
                        ->where('status', ZnsCampaignDelivery::STATUS_QUEUED)
                        ->orWhere(function (Builder $failedQuery) use ($maxDeliveryAttempts): void {
                            $failedQuery
                                ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
                                ->whereNotNull('next_retry_at')
                                ->where('next_retry_at', '<=', now())
                                ->where('attempt_count', '<', $maxDeliveryAttempts);
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $delivery instanceof ZnsCampaignDelivery) {
                return null;
            }

            $token = (string) Str::uuid();

            $delivery->processing_token = $token;
            $delivery->locked_at = now();
            $delivery->attempt_count = (int) $delivery->attempt_count + 1;
            $delivery->save();

            return [
                'delivery_id' => (int) $delivery->id,
                'processing_token' => $token,
                'idempotency_key' => (string) $delivery->idempotency_key,
                'phone' => trim((string) ($delivery->phone ?? '')),
                'normalized_phone' => trim((string) ($delivery->normalized_phone ?: $delivery->phone ?: '')),
                'template_id' => trim((string) ($delivery->template_id_snapshot ?? '')),
                'attempt_count' => (int) $delivery->attempt_count,
                'payload' => is_array($delivery->payload) ? $delivery->payload : [],
            ];
        }, 3);
    }

    protected function refreshCampaignSummary(ZnsCampaign $campaign): void
    {
        $campaign->refresh();

        $sentCount = (int) $campaign->deliveries()
            ->where('status', ZnsCampaignDelivery::STATUS_SENT)
            ->count();
        $failedCount = (int) $campaign->deliveries()
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->count();

        $hasRetryableFailures = $campaign->deliveries()
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->exists();

        $hasOutstandingQueuedOrLocked = $campaign->deliveries()
            ->where(function (Builder $query): void {
                $query->where('status', ZnsCampaignDelivery::STATUS_QUEUED)
                    ->orWhereNotNull('processing_token');
            })
            ->exists();

        $this->workflowService->syncSummaryStatus(
            campaign: $campaign,
            sentCount: $sentCount,
            failedCount: $failedCount,
            hasOutstandingQueuedOrLocked: $hasOutstandingQueuedOrLocked,
            hasRetryableFailures: $hasRetryableFailures,
        );
    }

    protected function reclaimStaleProcessingDeliveries(ZnsCampaign $campaign, int $maxDeliveryAttempts): int
    {
        $now = now();
        $lockedBefore = now()->subMinutes(self::DELIVERY_LOCK_TTL_MINUTES);

        $staleQuery = ZnsCampaignDelivery::query()
            ->where('zns_campaign_id', $campaign->id)
            ->whereNotNull('processing_token')
            ->where(function (Builder $query) use ($lockedBefore): void {
                $query->whereNull('locked_at')
                    ->orWhere('locked_at', '<=', $lockedBefore);
            });

        $terminal = (clone $staleQuery)
            ->where('attempt_count', '>=', $maxDeliveryAttempts)
            ->update([
                'status' => ZnsCampaignDelivery::STATUS_FAILED,
                'next_retry_at' => null,
                'processing_token' => null,
                'locked_at' => null,
                'error_message' => 'Stale processing lock reclaimed after max attempts reached.',
                'updated_at' => $now,
            ]);

        $retryable = (clone $staleQuery)
            ->where('attempt_count', '<', $maxDeliveryAttempts)
            ->update([
                'status' => ZnsCampaignDelivery::STATUS_FAILED,
                'next_retry_at' => $now,
                'processing_token' => null,
                'locked_at' => null,
                'error_message' => 'Stale processing lock reclaimed for retry.',
                'updated_at' => $now,
            ]);

        return $terminal + $retryable;
    }

    protected function isDeliveryLockExpired(ZnsCampaignDelivery $delivery): bool
    {
        if ($delivery->processing_token === null) {
            return true;
        }

        if ($delivery->locked_at === null) {
            return true;
        }

        return $delivery->locked_at->lte(now()->subMinutes(self::DELIVERY_LOCK_TTL_MINUTES));
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (! is_string($digits)) {
            return '';
        }

        $digits = trim($digits);
        if ($digits === '') {
            return '';
        }

        if (Str::startsWith($digits, '00')) {
            $digits = ltrim(substr($digits, 2), '0');
        }

        if (Str::startsWith($digits, '0')) {
            $digits = '84'.substr($digits, 1);
        } elseif (Str::startsWith($digits, '84')) {
            $digits = '84'.ltrim(substr($digits, 2), '0');
        }

        if (! Str::startsWith($digits, '84')) {
            return '';
        }

        $length = strlen($digits);

        if ($length < 10 || $length > 12) {
            return '';
        }

        return $digits;
    }

    protected function shouldSkipFailedDelivery(ZnsCampaignDelivery $delivery, int $maxDeliveryAttempts): bool
    {
        if ((int) $delivery->attempt_count >= $maxDeliveryAttempts) {
            return true;
        }

        if ($delivery->next_retry_at === null) {
            return true;
        }

        return $delivery->next_retry_at->isFuture();
    }

    /**
     * @param  array<string, mixed>  $sendResult
     */
    protected function isRetryableProviderFailure(array $sendResult): bool
    {
        $statusCode = isset($sendResult['status']) ? (int) $sendResult['status'] : null;

        if ($statusCode === null) {
            return true;
        }

        if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
            return false;
        }

        return true;
    }

    protected function assertZnsReadyForRun(): void
    {
        $enabled = ClinicRuntimeSettings::boolean('zns.enabled', false);

        if (! $enabled) {
            throw ValidationException::withMessages([
                'zns' => 'ZNS đang tắt, không thể chạy campaign.',
            ]);
        }

        $readinessReport = app(ZaloIntegrationService::class)->auditZnsReadiness();

        if (($readinessReport['score'] ?? 0) < 80) {
            throw ValidationException::withMessages([
                'zns' => 'ZNS chưa sẵn sàng: '.implode(' ', $readinessReport['issues'] ?? []),
            ]);
        }
    }

    protected function resolveTemplateId(ZnsCampaign $campaign): string
    {
        $templateId = trim((string) ($campaign->template_id ?? ''));

        if ($templateId !== '') {
            return $templateId;
        }

        $templateKey = Str::of((string) ($campaign->template_key ?? ''))->lower()->trim()->toString();

        return match ($templateKey) {
            'appointment', 'nhac_lich', 'nhac-lich' => trim((string) ClinicRuntimeSettings::get('zns.template_appointment', '')),
            'payment', 'nhac_thanh_toan', 'nhac-thanh-toan' => trim((string) ClinicRuntimeSettings::get('zns.template_payment', '')),
            default => trim((string) ClinicRuntimeSettings::get('zns.template_appointment', '')),
        };
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     * @return array<string, mixed>
     */
    protected function buildProviderPayload(
        ZnsCampaign $campaign,
        string $templateId,
        string $normalizedPhone,
        string $idempotencyKey,
        array $messagePayload,
    ): array {
        return [
            'phone' => $normalizedPhone,
            'template_id' => $templateId,
            'template_data' => $messagePayload,
            'tracking_id' => $idempotencyKey,
            'campaign_code' => (string) $campaign->code,
        ];
    }

    protected function deliveryIdempotencyKey(int $campaignId, string $normalizedPhone, string $templateId): string
    {
        return hash('sha256', implode('|', [
            'zns-v2',
            $campaignId,
            $normalizedPhone !== '' ? $normalizedPhone : 'missing-phone',
            $templateId,
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $providerPayload
     * @param  array<string, mixed>  $sendResult
     */
    protected function finalizeClaimedDelivery(
        int $deliveryId,
        string $processingToken,
        ?array $providerPayload,
        array $sendResult,
        string $status,
        mixed $nextRetryAt,
    ): bool {
        return DB::transaction(function () use (
            $deliveryId,
            $processingToken,
            $providerPayload,
            $sendResult,
            $status,
            $nextRetryAt,
        ): bool {
            $delivery = ZnsCampaignDelivery::query()
                ->whereKey($deliveryId)
                ->where('processing_token', $processingToken)
                ->lockForUpdate()
                ->first();

            if (! $delivery instanceof ZnsCampaignDelivery) {
                return false;
            }

            $delivery->status = $status;
            $delivery->provider_message_id = $status === ZnsCampaignDelivery::STATUS_SENT
                ? ($sendResult['provider_message_id'] ?? null)
                : null;
            $delivery->provider_status_code = $sendResult['provider_status_code'] ?? null;
            $delivery->provider_response = $this->payloadSanitizer->sanitizeProviderResponse(
                is_array($sendResult['response'] ?? null) ? $sendResult['response'] : null,
            );
            $delivery->error_message = $status === ZnsCampaignDelivery::STATUS_SENT
                ? null
                : (string) ($sendResult['error'] ?? 'ZNS provider request failed.');
            $delivery->next_retry_at = $nextRetryAt;
            $delivery->sent_at = $status === ZnsCampaignDelivery::STATUS_SENT ? now() : $delivery->sent_at;
            $delivery->payload = $providerPayload === null
                ? $delivery->payload
                : array_merge(is_array($delivery->payload) ? $delivery->payload : [], [
                    'provider_request_summary' => $this->payloadSanitizer->sanitizeProviderRequest($providerPayload),
                ]);
            $delivery->processing_token = null;
            $delivery->locked_at = null;
            $delivery->save();

            return true;
        }, 3);
    }
}
