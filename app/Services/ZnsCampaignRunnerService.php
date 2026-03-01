<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Patient;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Support\Facades\DB;

class ZnsCampaignRunnerService
{
    /**
     * @return array{processed:int,sent:int,failed:int,skipped:int}
     */
    public function runCampaign(ZnsCampaign $campaign): array
    {
        $campaign->refresh();

        $runnableStatuses = [
            ZnsCampaign::STATUS_DRAFT,
            ZnsCampaign::STATUS_SCHEDULED,
            ZnsCampaign::STATUS_RUNNING,
            ZnsCampaign::STATUS_FAILED,
        ];

        if (! in_array($campaign->status, $runnableStatuses, true)) {
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
            foreach ($targets as $target) {
                $idempotencyKey = hash('sha256', implode('|', [
                    $campaign->id,
                    $target['patient_id'] ?? 'none',
                    $target['customer_id'] ?? 'none',
                    $target['phone'],
                ]));

                $delivery = ZnsCampaignDelivery::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'zns_campaign_id' => $campaign->id,
                        'patient_id' => $target['patient_id'] ?? null,
                        'customer_id' => $target['customer_id'] ?? null,
                        'phone' => $target['phone'],
                        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
                        'payload' => [
                            'template_key' => $campaign->template_key,
                            'template_id' => $campaign->template_id,
                            'message' => $campaign->message_payload,
                        ],
                    ],
                );

                if (! $delivery->wasRecentlyCreated && in_array($delivery->status, [ZnsCampaignDelivery::STATUS_SENT, ZnsCampaignDelivery::STATUS_SKIPPED], true)) {
                    $skipped++;

                    continue;
                }

                $processed++;

                if ($target['phone'] === '') {
                    $delivery->status = ZnsCampaignDelivery::STATUS_FAILED;
                    $delivery->error_message = 'Thiếu số điện thoại nhận ZNS.';
                    $delivery->save();
                    $failed++;

                    continue;
                }

                $delivery->status = ZnsCampaignDelivery::STATUS_SENT;
                $delivery->sent_at = now();
                $delivery->provider_message_id = 'mock-'.substr($delivery->idempotency_key, 0, 12);
                $delivery->error_message = null;
                $delivery->save();
                $sent++;
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
     * @return array<int, array{patient_id:int|null,customer_id:int|null,phone:string}>
     */
    protected function resolveTargets(ZnsCampaign $campaign): array
    {
        $patientQuery = Patient::query()
            ->select(['id', 'customer_id', 'phone', 'first_branch_id'])
            ->when($campaign->branch_id, fn ($query) => $query->where('first_branch_id', (int) $campaign->branch_id))
            ->when($campaign->audience_last_visit_before_days, function ($query) use ($campaign): void {
                $cutoff = now()->subDays((int) $campaign->audience_last_visit_before_days);
                $query->where(function ($innerQuery) use ($cutoff): void {
                    $innerQuery->whereDoesntHave('appointments')
                        ->orWhereHas('appointments', fn ($appointmentQuery) => $appointmentQuery->where('date', '<=', $cutoff));
                });
            })
            ->limit(500);

        $patients = $patientQuery->get();

        if ($campaign->audience_source) {
            $allowedCustomerIds = Customer::query()
                ->where('source', $campaign->audience_source)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $patients = $patients
                ->filter(fn (Patient $patient): bool => in_array((int) ($patient->customer_id ?? 0), $allowedCustomerIds, true))
                ->values();
        }

        return $patients
            ->map(function (Patient $patient): array {
                return [
                    'patient_id' => $patient->id,
                    'customer_id' => $patient->customer_id ? (int) $patient->customer_id : null,
                    'phone' => trim((string) ($patient->phone ?? '')),
                ];
            })
            ->all();
    }
}
