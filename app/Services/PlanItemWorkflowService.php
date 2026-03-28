<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlanItem;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanItemWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(PlanItem $planItem, array $data): array
    {
        $incomingStatus = PlanItem::normalizeStatus($data['status'] ?? $planItem->status) ?? PlanItem::DEFAULT_STATUS;
        $currentStatus = PlanItem::normalizeStatus($planItem->status) ?? PlanItem::DEFAULT_STATUS;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai hang muc chi duoc thay doi qua PlanItemWorkflowService.',
            ]);
        }

        $data['status'] = $planItem->status;

        return $data;
    }

    public function startTreatment(PlanItem $planItem, ?string $reason = null, ?int $actorId = null): PlanItem
    {
        Gate::authorize('update', $planItem);

        return $this->transition(
            planItem: $planItem,
            auditAction: AuditLog::ACTION_UPDATE,
            trigger: 'manual_start',
            reason: $reason,
            actorId: $actorId,
            mutator: function (PlanItem $lockedPlanItem): void {
                $this->assertCanStartTreatment($lockedPlanItem);

                if ($lockedPlanItem->status !== PlanItem::STATUS_PENDING) {
                    throw ValidationException::withMessages([
                        'status' => 'Chi co the bat dau hang muc dang cho thuc hien.',
                    ]);
                }

                $lockedPlanItem->forceFill([
                    'status' => PlanItem::STATUS_IN_PROGRESS,
                    'started_at' => $lockedPlanItem->started_at ?? now()->toDateString(),
                ])->save();

                $lockedPlanItem->treatmentPlan?->updateProgress();
            },
        );
    }

    public function completeVisit(PlanItem $planItem, ?string $reason = null, ?int $actorId = null): PlanItem
    {
        Gate::authorize('update', $planItem);

        return $this->transition(
            planItem: $planItem,
            auditAction: AuditLog::ACTION_UPDATE,
            trigger: 'complete_visit',
            reason: $reason,
            actorId: $actorId,
            mutator: function (PlanItem $lockedPlanItem): void {
                $this->assertCanStartTreatment($lockedPlanItem);

                if ($lockedPlanItem->status === PlanItem::STATUS_CANCELLED) {
                    throw ValidationException::withMessages([
                        'status' => 'Khong the cap nhat tien do cho hang muc da huy.',
                    ]);
                }

                if ($lockedPlanItem->status === PlanItem::STATUS_COMPLETED) {
                    throw ValidationException::withMessages([
                        'status' => 'Hang muc da hoan thanh.',
                    ]);
                }

                $lockedPlanItem->completeVisit();
            },
            resolveAuditActionUsing: static fn (PlanItem $lockedPlanItem): string => $lockedPlanItem->status === PlanItem::STATUS_COMPLETED
                ? AuditLog::ACTION_COMPLETE
                : AuditLog::ACTION_UPDATE,
        );
    }

    public function completeTreatment(PlanItem $planItem, ?string $reason = null, ?int $actorId = null): PlanItem
    {
        Gate::authorize('update', $planItem);

        return $this->transition(
            planItem: $planItem,
            auditAction: AuditLog::ACTION_COMPLETE,
            trigger: 'manual_complete',
            reason: $reason,
            actorId: $actorId,
            mutator: function (PlanItem $lockedPlanItem): void {
                $this->assertCanStartTreatment($lockedPlanItem);

                if (in_array($lockedPlanItem->status, [PlanItem::STATUS_COMPLETED, PlanItem::STATUS_CANCELLED], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Khong the hoan thanh hang muc da dong.',
                    ]);
                }

                $lockedPlanItem->forceFill([
                    'status' => PlanItem::STATUS_COMPLETED,
                    'completed_visits' => max(1, (int) $lockedPlanItem->required_visits),
                    'progress_percentage' => 100,
                    'started_at' => $lockedPlanItem->started_at ?? now()->toDateString(),
                    'completed_at' => now()->toDateString(),
                ])->save();

                $lockedPlanItem->treatmentPlan?->updateProgress();
            },
        );
    }

    public function cancel(PlanItem $planItem, ?string $reason = null, ?int $actorId = null): PlanItem
    {
        Gate::authorize('update', $planItem);

        return $this->transition(
            planItem: $planItem,
            auditAction: AuditLog::ACTION_CANCEL,
            trigger: 'manual_cancel',
            reason: $reason,
            actorId: $actorId,
            mutator: function (PlanItem $lockedPlanItem): void {
                if (in_array($lockedPlanItem->status, [PlanItem::STATUS_COMPLETED, PlanItem::STATUS_CANCELLED], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Chi co the huy hang muc dang cho hoac dang thuc hien.',
                    ]);
                }

                $lockedPlanItem->forceFill([
                    'status' => PlanItem::STATUS_CANCELLED,
                    'completed_at' => null,
                ])->save();

                $lockedPlanItem->treatmentPlan?->updateProgress();
            },
        );
    }

    protected function assertCanStartTreatment(PlanItem $planItem): void
    {
        if ($planItem->canStartTreatment()) {
            return;
        }

        throw ValidationException::withMessages([
            'approval_status' => 'Hang muc chua duoc benh nhan duyet. Khong the cap nhat tien do dieu tri.',
        ]);
    }

    /**
     * @param  callable(PlanItem): void  $mutator
     * @param  callable(PlanItem): string|null  $resolveAuditActionUsing
     */
    protected function transition(
        PlanItem $planItem,
        string $auditAction,
        string $trigger,
        ?string $reason,
        ?int $actorId,
        callable $mutator,
        ?callable $resolveAuditActionUsing = null,
    ): PlanItem {
        return DB::transaction(function () use ($planItem, $auditAction, $trigger, $reason, $actorId, $mutator, $resolveAuditActionUsing): PlanItem {
            $lockedPlanItem = $this->lockPlanItem($planItem);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = PlanItem::normalizeStatus($lockedPlanItem->status) ?? PlanItem::DEFAULT_STATUS;
            $beforeCompletedVisits = (int) $lockedPlanItem->completed_visits;
            $beforeProgress = (int) $lockedPlanItem->progress_percentage;

            $mutator($lockedPlanItem);

            $lockedPlanItem->refresh()->loadMissing('treatmentPlan:id,patient_id,branch_id');

            $resolvedAuditAction = $resolveAuditActionUsing instanceof \Closure
                ? $resolveAuditActionUsing($lockedPlanItem)
                : $auditAction;

            $this->recordAudit(
                planItem: $lockedPlanItem,
                action: $resolvedAuditAction,
                actorId: $resolvedActorId,
                metadata: WorkflowAuditMetadata::transition(
                    fromStatus: $fromStatus,
                    toStatus: PlanItem::normalizeStatus($lockedPlanItem->status) ?? PlanItem::DEFAULT_STATUS,
                    reason: $reason,
                    metadata: [
                        'trigger' => $trigger,
                        'plan_item_id' => (int) $lockedPlanItem->getKey(),
                        'plan_item_name' => $lockedPlanItem->name,
                        'treatment_plan_id' => (int) $lockedPlanItem->treatment_plan_id,
                        'patient_id' => $lockedPlanItem->treatmentPlan?->patient_id,
                        'branch_id' => $lockedPlanItem->resolveBranchId(),
                        'approval_status' => $lockedPlanItem->approval_status,
                        'required_visits' => (int) $lockedPlanItem->required_visits,
                        'completed_visits_from' => $beforeCompletedVisits,
                        'completed_visits_to' => (int) $lockedPlanItem->completed_visits,
                        'progress_from' => $beforeProgress,
                        'progress_to' => (int) $lockedPlanItem->progress_percentage,
                    ],
                ),
            );

            return $lockedPlanItem;
        }, 3);
    }

    protected function lockPlanItem(PlanItem $planItem): PlanItem
    {
        return PlanItem::query()
            ->with('treatmentPlan:id,patient_id,branch_id')
            ->lockForUpdate()
            ->findOrFail($planItem->getKey());
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(PlanItem $planItem, string $action, ?int $actorId, array $metadata): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_PLAN_ITEM,
            entityId: (int) $planItem->getKey(),
            action: $action,
            actorId: $actorId,
            metadata: $metadata,
        );
    }
}
