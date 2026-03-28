<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PopupAnnouncement;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PopupAnnouncementWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $status = PopupAnnouncement::normalizeStatusValue($data['status'] ?? PopupAnnouncement::STATUS_DRAFT)
            ?? PopupAnnouncement::STATUS_DRAFT;

        $data['status'] = $status;

        if ($status === PopupAnnouncement::STATUS_PUBLISHED) {
            $data['starts_at'] = filled($data['starts_at'] ?? null) ? $data['starts_at'] : now();
            $data['published_at'] = filled($data['published_at'] ?? null) ? $data['published_at'] : now();
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(PopupAnnouncement $announcement, array $data): array
    {
        $incomingStatus = PopupAnnouncement::normalizeStatusValue($data['status'] ?? $announcement->status)
            ?? PopupAnnouncement::STATUS_DRAFT;
        $currentStatus = PopupAnnouncement::normalizeStatusValue($announcement->status)
            ?? PopupAnnouncement::STATUS_DRAFT;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai popup chi duoc thay doi qua PopupAnnouncementWorkflowService.',
            ]);
        }

        $data['status'] = $announcement->status;
        $data['published_at'] = $announcement->published_at;

        return $data;
    }

    public function publish(PopupAnnouncement $announcement, ?string $reason = null, ?int $actorId = null): PopupAnnouncement
    {
        Gate::authorize('update', $announcement);

        return $this->transition(
            announcement: $announcement,
            toStatus: PopupAnnouncement::STATUS_PUBLISHED,
            actorId: $actorId,
            attributes: [
                'starts_at' => $announcement->starts_at ?? now(),
                'published_at' => $announcement->published_at ?? now(),
            ],
            auditAction: AuditLog::ACTION_UPDATE,
            metadata: [
                'trigger' => 'manual_publish',
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
            ],
        );
    }

    public function cancel(PopupAnnouncement $announcement, ?string $reason = null, ?int $actorId = null): PopupAnnouncement
    {
        Gate::authorize('update', $announcement);

        return $this->transition(
            announcement: $announcement,
            toStatus: PopupAnnouncement::STATUS_CANCELLED,
            actorId: $actorId,
            attributes: [],
            auditAction: AuditLog::ACTION_CANCEL,
            metadata: [
                'trigger' => 'manual_cancel',
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
            ],
        );
    }

    public function markPublishedFromDispatch(PopupAnnouncement $announcement, ?int $actorId = null): PopupAnnouncement
    {
        return $this->transition(
            announcement: $announcement,
            toStatus: PopupAnnouncement::STATUS_PUBLISHED,
            actorId: $actorId,
            attributes: [
                'starts_at' => $announcement->starts_at ?? now(),
                'published_at' => $announcement->published_at ?? now(),
            ],
            auditAction: AuditLog::ACTION_UPDATE,
            metadata: [
                'trigger' => 'dispatch_due',
                'reason' => 'dispatch_due',
            ],
            authorize: false,
        );
    }

    public function markFailedNoRecipients(PopupAnnouncement $announcement, ?int $actorId = null): PopupAnnouncement
    {
        return $this->transition(
            announcement: $announcement,
            toStatus: PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT,
            actorId: $actorId,
            attributes: [],
            auditAction: AuditLog::ACTION_FAIL,
            metadata: [
                'trigger' => 'dispatch_due',
                'reason' => 'no_eligible_recipients',
            ],
            authorize: false,
        );
    }

    public function expire(PopupAnnouncement $announcement, ?int $actorId = null): PopupAnnouncement
    {
        return $this->transition(
            announcement: $announcement,
            toStatus: PopupAnnouncement::STATUS_EXPIRED,
            actorId: $actorId,
            attributes: [],
            auditAction: AuditLog::ACTION_UPDATE,
            metadata: [
                'trigger' => 'expiry_sweep',
                'reason' => 'end_time_reached',
            ],
            authorize: false,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    protected function transition(
        PopupAnnouncement $announcement,
        string $toStatus,
        ?int $actorId,
        array $attributes,
        string $auditAction,
        array $metadata = [],
        bool $authorize = true,
    ): PopupAnnouncement {
        if ($authorize) {
            Gate::authorize('update', $announcement);
        }

        return DB::transaction(function () use ($announcement, $toStatus, $actorId, $attributes, $auditAction, $metadata): PopupAnnouncement {
            $lockedAnnouncement = PopupAnnouncement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            $fromStatus = PopupAnnouncement::normalizeStatusValue($lockedAnnouncement->status) ?? PopupAnnouncement::STATUS_DRAFT;
            $targetStatus = PopupAnnouncement::normalizeStatusValue($toStatus) ?? $toStatus;
            $resolvedActorId = $this->resolveActorId($actorId);

            if (! PopupAnnouncement::canTransitionStatus($fromStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Không thể chuyển popup từ "%s" sang "%s".',
                        PopupAnnouncement::statusLabel($fromStatus),
                        PopupAnnouncement::statusLabel($targetStatus),
                    ),
                ]);
            }

            $payload = $attributes;

            if ($fromStatus !== $targetStatus) {
                $payload['status'] = $targetStatus;
            }

            if ($resolvedActorId !== null) {
                $payload['updated_by'] = $resolvedActorId;
            }

            if ($payload !== []) {
                PopupAnnouncement::runWithinManagedWorkflow(function () use ($lockedAnnouncement, $payload): void {
                    $lockedAnnouncement->forceFill($payload)->save();
                });
            }

            $lockedAnnouncement->refresh();

            if ($fromStatus !== $targetStatus) {
                $this->recordAudit(
                    announcement: $lockedAnnouncement,
                    action: $auditAction,
                    actorId: $resolvedActorId,
                    metadata: WorkflowAuditMetadata::transition(
                        fromStatus: $fromStatus,
                        toStatus: $targetStatus,
                        reason: data_get($metadata, 'reason'),
                        metadata: $metadata,
                    ),
                );
            }

            return $lockedAnnouncement;
        }, 3);
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(PopupAnnouncement $announcement, string $action, ?int $actorId, array $metadata = []): void
    {
        $targetBranchIds = collect($announcement->target_branch_ids ?? [])
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId))
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->values()
            ->all();

        $primaryBranchId = count($targetBranchIds) === 1 ? $targetBranchIds[0] : null;

        AuditLog::record(
            entityType: AuditLog::ENTITY_POPUP_ANNOUNCEMENT,
            entityId: (int) $announcement->getKey(),
            action: $action,
            actorId: $actorId,
            branchId: $primaryBranchId,
            metadata: array_merge($metadata, [
                'popup_announcement_id' => (int) $announcement->getKey(),
                'target_branch_ids' => $targetBranchIds,
                'target_role_names' => collect($announcement->target_role_names ?? [])->values()->all(),
            ]),
        );
    }
}
