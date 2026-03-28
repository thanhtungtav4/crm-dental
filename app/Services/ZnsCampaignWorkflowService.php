<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ZnsCampaign;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ZnsCampaignWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $data['status'] = ZnsCampaign::STATUS_DRAFT;
        $data['scheduled_at'] = null;
        $data['started_at'] = null;
        $data['finished_at'] = null;
        $data['sent_count'] = 0;
        $data['failed_count'] = 0;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(ZnsCampaign $campaign, array $data): array
    {
        $incomingStatus = ZnsCampaign::normalizeStatusValue($data['status'] ?? $campaign->status)
            ?? ZnsCampaign::STATUS_DRAFT;
        $currentStatus = ZnsCampaign::normalizeStatusValue($campaign->status) ?? ZnsCampaign::STATUS_DRAFT;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai campaign ZNS chi duoc thay doi qua ZnsCampaignWorkflowService.',
            ]);
        }

        $data['status'] = $campaign->status;
        $data['scheduled_at'] = $campaign->scheduled_at;
        $data['started_at'] = $campaign->started_at;
        $data['finished_at'] = $campaign->finished_at;
        $data['sent_count'] = $campaign->sent_count;
        $data['failed_count'] = $campaign->failed_count;

        return $data;
    }

    public function schedule(
        ZnsCampaign $campaign,
        mixed $scheduledAt = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): ZnsCampaign {
        $this->authorizeUpdate($campaign);

        return $this->transition(
            campaign: $campaign,
            toStatus: ZnsCampaign::STATUS_SCHEDULED,
            actorId: $actorId,
            attributes: [
                'scheduled_at' => $scheduledAt ?: now()->addMinutes(5),
                'started_at' => null,
                'finished_at' => null,
            ],
            auditAction: AuditLog::ACTION_UPDATE,
            metadata: [
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
            ],
        );
    }

    public function runNow(ZnsCampaign $campaign, ?string $reason = null, ?int $actorId = null): ZnsCampaign
    {
        $this->authorizeUpdate($campaign);

        return $this->transition(
            campaign: $campaign,
            toStatus: ZnsCampaign::STATUS_RUNNING,
            actorId: $actorId,
            attributes: [
                'scheduled_at' => $campaign->scheduled_at ?? now(),
                'started_at' => $campaign->started_at ?? now(),
                'finished_at' => null,
            ],
            auditAction: AuditLog::ACTION_RUN,
            metadata: [
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
                'trigger' => 'manual_run',
            ],
        );
    }

    public function cancel(ZnsCampaign $campaign, ?string $reason = null, ?int $actorId = null): ZnsCampaign
    {
        $this->authorizeUpdate($campaign);

        $resolvedReason = WorkflowAuditMetadata::normalizeReason($reason);

        if ($resolvedReason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Vui long nhap ly do huy campaign ZNS.',
            ]);
        }

        return $this->transition(
            campaign: $campaign,
            toStatus: ZnsCampaign::STATUS_CANCELLED,
            actorId: $actorId,
            attributes: [
                'finished_at' => now(),
            ],
            auditAction: AuditLog::ACTION_CANCEL,
            metadata: [
                'reason' => $resolvedReason,
            ],
        );
    }

    public function markRunning(ZnsCampaign $campaign, ?int $actorId = null, ?string $reason = null): ZnsCampaign
    {
        return $this->transition(
            campaign: $campaign,
            toStatus: ZnsCampaign::STATUS_RUNNING,
            actorId: $actorId,
            attributes: [
                'started_at' => $campaign->started_at ?? now(),
                'finished_at' => null,
            ],
            auditAction: AuditLog::ACTION_RUN,
            metadata: [
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
                'trigger' => 'runner_claim',
            ],
            authorize: false,
        );
    }

    public function markFailed(ZnsCampaign $campaign, ?string $reason = null, ?int $actorId = null): ZnsCampaign
    {
        return $this->transition(
            campaign: $campaign,
            toStatus: ZnsCampaign::STATUS_FAILED,
            actorId: $actorId,
            attributes: [
                'finished_at' => now(),
            ],
            auditAction: AuditLog::ACTION_FAIL,
            metadata: [
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
            ],
            authorize: false,
        );
    }

    public function syncSummaryStatus(
        ZnsCampaign $campaign,
        int $sentCount,
        int $failedCount,
        bool $hasOutstandingQueuedOrLocked,
        bool $hasRetryableFailures,
        ?int $actorId = null,
    ): ZnsCampaign {
        $targetStatus = $hasOutstandingQueuedOrLocked
            ? ZnsCampaign::STATUS_RUNNING
            : ($hasRetryableFailures || ($failedCount > 0 && $sentCount === 0)
                ? ZnsCampaign::STATUS_FAILED
                : ZnsCampaign::STATUS_COMPLETED);

        $attributes = [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'finished_at' => $hasOutstandingQueuedOrLocked ? null : now(),
        ];

        if ($targetStatus === ZnsCampaign::STATUS_RUNNING) {
            $attributes['started_at'] = $campaign->started_at ?? now();
        }

        return $this->transition(
            campaign: $campaign,
            toStatus: $targetStatus,
            actorId: $actorId,
            attributes: $attributes,
            auditAction: match ($targetStatus) {
                ZnsCampaign::STATUS_COMPLETED => AuditLog::ACTION_COMPLETE,
                ZnsCampaign::STATUS_FAILED => AuditLog::ACTION_FAIL,
                default => AuditLog::ACTION_RUN,
            },
            metadata: [
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'has_outstanding_queued_or_locked' => $hasOutstandingQueuedOrLocked,
                'has_retryable_failures' => $hasRetryableFailures,
                'trigger' => 'runner_summary',
            ],
            authorize: false,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    protected function transition(
        ZnsCampaign $campaign,
        string $toStatus,
        ?int $actorId,
        array $attributes,
        string $auditAction,
        array $metadata = [],
        bool $authorize = true,
    ): ZnsCampaign {
        if ($authorize) {
            $this->authorizeUpdate($campaign);
        }

        return DB::transaction(function () use ($campaign, $toStatus, $actorId, $attributes, $auditAction, $metadata): ZnsCampaign {
            $lockedCampaign = ZnsCampaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->getKey());

            $fromStatus = ZnsCampaign::normalizeStatusValue($lockedCampaign->status) ?? ZnsCampaign::STATUS_DRAFT;
            $targetStatus = ZnsCampaign::normalizeStatusValue($toStatus) ?? $toStatus;

            if ($fromStatus === $targetStatus) {
                if ($attributes !== []) {
                    ZnsCampaign::runWithinManagedWorkflow(function () use ($lockedCampaign, $attributes): void {
                        $lockedCampaign->forceFill($attributes)->save();
                    });
                }

                return $lockedCampaign->fresh();
            }

            if (! ZnsCampaign::canTransitionStatus($fromStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Khong the chuyen campaign ZNS tu "%s" sang "%s".',
                        ZnsCampaign::statusLabel($fromStatus),
                        ZnsCampaign::statusLabel($targetStatus),
                    ),
                ]);
            }

            ZnsCampaign::runWithinManagedWorkflow(function () use ($lockedCampaign, $targetStatus, $attributes): void {
                $lockedCampaign->forceFill(array_merge($attributes, [
                    'status' => $targetStatus,
                ]))->save();
            });

            $lockedCampaign->refresh();

            $this->recordAudit(
                campaign: $lockedCampaign,
                action: $auditAction,
                actorId: $this->resolveActorId($actorId),
                metadata: WorkflowAuditMetadata::transition(
                    fromStatus: $fromStatus,
                    toStatus: $targetStatus,
                    reason: data_get($metadata, 'reason'),
                    metadata: $metadata,
                ),
            );

            return $lockedCampaign;
        }, 3);
    }

    protected function authorizeUpdate(ZnsCampaign $campaign): void
    {
        if (! auth()->check()) {
            return;
        }

        Gate::authorize('update', $campaign);
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(ZnsCampaign $campaign, string $action, ?int $actorId, array $metadata = []): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: (int) $campaign->getKey(),
            action: $action,
            actorId: $actorId,
            metadata: array_merge($metadata, [
                'channel' => 'zns_campaign',
                'campaign_id' => (int) $campaign->getKey(),
                'campaign_code' => $campaign->code,
                'campaign_name' => $campaign->name,
                'branch_id' => $campaign->branch_id,
            ]),
            branchId: $campaign->branch_id,
        );
    }
}
