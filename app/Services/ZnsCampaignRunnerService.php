<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ZnsCampaignRunnerService
{
    public function __construct(
        private readonly ZnsProviderClient $providerClient,
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

        if (in_array($campaign->status, [ZnsCampaign::STATUS_DRAFT, ZnsCampaign::STATUS_SCHEDULED, ZnsCampaign::STATUS_FAILED], true)) {
            $campaign->status = ZnsCampaign::STATUS_RUNNING;
            $campaign->started_at = $campaign->started_at ?? now();
            $campaign->save();
        }

        $targets = $this->resolveTargets($campaign);

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        DB::transaction(function () use ($campaign, $targets, &$processed, &$sent, &$failed, &$skipped): void {
            $templateId = $this->resolveTemplateId($campaign);
            if ($templateId === '') {
                throw ValidationException::withMessages([
                    'template_id' => 'Campaign chưa có template_id hợp lệ để gửi ZNS.',
                ]);
            }

            foreach ($targets as $target) {
                $normalizedPhone = $target['normalized_phone'];
                $idempotencyKey = hash('sha256', implode('|', [
                    'zns-v2',
                    $campaign->id,
                    $normalizedPhone !== '' ? $normalizedPhone : 'missing-phone',
                    $templateId,
                ]));

                $delivery = ZnsCampaignDelivery::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'zns_campaign_id' => $campaign->id,
                        'patient_id' => $target['patient_id'] ?? null,
                        'customer_id' => $target['customer_id'] ?? null,
                        'branch_id' => $campaign->branch_id,
                        'phone' => $target['phone'],
                        'normalized_phone' => $normalizedPhone,
                        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
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

                if (! $delivery->wasRecentlyCreated && in_array($delivery->status, [ZnsCampaignDelivery::STATUS_SENT, ZnsCampaignDelivery::STATUS_SKIPPED], true)) {
                    $skipped++;

                    continue;
                }

                if (
                    ! $delivery->wasRecentlyCreated
                    && $delivery->status === ZnsCampaignDelivery::STATUS_FAILED
                    && $delivery->next_retry_at !== null
                    && $delivery->next_retry_at->isFuture()
                ) {
                    $skipped++;

                    continue;
                }

                $processed++;
                $delivery->attempt_count = (int) $delivery->attempt_count + 1;

                if ($normalizedPhone === '') {
                    $delivery->status = ZnsCampaignDelivery::STATUS_FAILED;
                    $delivery->error_message = 'Thiếu số điện thoại nhận ZNS.';
                    $delivery->provider_message_id = null;
                    $delivery->provider_status_code = 'validation_missing_phone';
                    $delivery->provider_response = null;
                    $delivery->next_retry_at = null;
                    $delivery->save();
                    $failed++;

                    continue;
                }

                $providerPayload = $this->buildProviderPayload(
                    campaign: $campaign,
                    templateId: $templateId,
                    target: $target,
                    idempotencyKey: $idempotencyKey,
                );

                $sendResult = $this->providerClient->sendTemplate($providerPayload);

                if (($sendResult['success'] ?? false) === true) {
                    $delivery->status = ZnsCampaignDelivery::STATUS_SENT;
                    $delivery->sent_at = now();
                    $delivery->provider_message_id = $sendResult['provider_message_id'];
                    $delivery->provider_status_code = $sendResult['provider_status_code'];
                    $delivery->provider_response = $sendResult['response'];
                    $delivery->error_message = null;
                    $delivery->next_retry_at = null;
                    $delivery->save();
                    $sent++;

                    continue;
                }

                $delivery->status = ZnsCampaignDelivery::STATUS_FAILED;
                $delivery->provider_message_id = null;
                $delivery->provider_status_code = $sendResult['provider_status_code'] ?? null;
                $delivery->provider_response = $sendResult['response'] ?? null;
                $delivery->error_message = $sendResult['error'] ?? 'ZNS provider request failed.';
                $delivery->next_retry_at = now()->addMinutes(15);
                $delivery->save();
                $failed++;
            }

            $campaign->sent_count = (int) $campaign->deliveries()->where('status', ZnsCampaignDelivery::STATUS_SENT)->count();
            $campaign->failed_count = (int) $campaign->deliveries()->where('status', ZnsCampaignDelivery::STATUS_FAILED)->count();
            $campaign->status = $campaign->failed_count > 0 && $campaign->sent_count === 0
                ? ZnsCampaign::STATUS_FAILED
                : ZnsCampaign::STATUS_COMPLETED;
            $campaign->finished_at = now();
            $campaign->save();
        }, 3);

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<int, array{patient_id:int|null,customer_id:int|null,phone:string,normalized_phone:string}>
     */
    protected function resolveTargets(ZnsCampaign $campaign): array
    {
        $latestAppointmentSubquery = Appointment::query()
            ->selectRaw('patient_id, MAX(date) as latest_appointment_date')
            ->groupBy('patient_id');

        $patientQuery = Patient::query()
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
            ->when($campaign->audience_last_visit_before_days, function ($query) use ($campaign): void {
                $cutoff = now()->subDays((int) $campaign->audience_last_visit_before_days);
                $query->where(function (Builder $innerQuery) use ($cutoff): void {
                    $innerQuery->whereNull('latest_appointments.latest_appointment_date')
                        ->orWhere('latest_appointments.latest_appointment_date', '<=', $cutoff);
                });
            })
            ->orderByDesc('patients.id')
            ->limit(500);

        $patients = $patientQuery->get();

        return $patients
            ->map(function (Patient $patient): array {
                $rawPhone = trim((string) ($patient->phone ?? ''));

                return [
                    'patient_id' => $patient->id,
                    'customer_id' => $patient->customer_id ? (int) $patient->customer_id : null,
                    'phone' => $rawPhone,
                    'normalized_phone' => $this->normalizePhone($rawPhone),
                ];
            })
            ->unique(static fn (array $target): string => $target['normalized_phone'] !== ''
                ? $target['normalized_phone']
                : 'missing-'.$target['patient_id'])
            ->values()
            ->all();
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (! is_string($digits)) {
            return '';
        }

        return trim($digits);
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
     * @param  array{patient_id:int|null,customer_id:int|null,phone:string,normalized_phone:string}  $target
     * @return array<string, mixed>
     */
    protected function buildProviderPayload(
        ZnsCampaign $campaign,
        string $templateId,
        array $target,
        string $idempotencyKey,
    ): array {
        return [
            'phone' => $target['normalized_phone'],
            'template_id' => $templateId,
            'template_data' => is_array($campaign->message_payload) ? $campaign->message_payload : [],
            'tracking_id' => $idempotencyKey,
            'campaign_code' => (string) $campaign->code,
        ];
    }
}
