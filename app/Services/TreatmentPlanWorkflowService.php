<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TreatmentPlan;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TreatmentPlanWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $data['status'] = TreatmentPlan::STATUS_DRAFT;
        $data['approved_by'] = null;
        $data['approved_at'] = null;
        $data['actual_start_date'] = null;
        $data['actual_end_date'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(TreatmentPlan $plan, array $data): array
    {
        $incomingStatus = TreatmentPlan::normalizeStatusValue($data['status'] ?? $plan->status) ?? TreatmentPlan::DEFAULT_STATUS;
        $currentStatus = TreatmentPlan::normalizeStatusValue($plan->status) ?? TreatmentPlan::DEFAULT_STATUS;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai ke hoach chi duoc thay doi qua TreatmentPlanWorkflowService.',
            ]);
        }

        $data['status'] = $plan->status;
        $data['approved_by'] = $plan->approved_by;
        $data['approved_at'] = $plan->approved_at;
        $data['actual_start_date'] = $plan->actual_start_date;
        $data['actual_end_date'] = $plan->actual_end_date;

        return $data;
    }

    public function approve(TreatmentPlan $plan, ?int $actorId = null): TreatmentPlan
    {
        Gate::authorize('update', $plan);
        ActionGate::authorize(
            ActionPermission::PLAN_APPROVAL,
            'Ban khong co quyen duyet ke hoach dieu tri.',
        );

        return $this->transition(
            plan: $plan,
            toStatus: TreatmentPlan::STATUS_APPROVED,
            actorId: $actorId,
            attributes: [
                'approved_by' => $actorId ?? auth()->id(),
                'approved_at' => now(),
            ],
            auditAction: AuditLog::ACTION_APPROVE,
        );
    }

    public function start(TreatmentPlan $plan, ?int $actorId = null): TreatmentPlan
    {
        Gate::authorize('update', $plan);

        return $this->transition(
            plan: $plan,
            toStatus: TreatmentPlan::STATUS_IN_PROGRESS,
            actorId: $actorId,
            attributes: [
                'actual_start_date' => $plan->actual_start_date ?? now()->toDateString(),
            ],
            auditAction: AuditLog::ACTION_UPDATE,
        );
    }

    public function complete(TreatmentPlan $plan, ?int $actorId = null): TreatmentPlan
    {
        Gate::authorize('update', $plan);

        return DB::transaction(function () use ($plan, $actorId): TreatmentPlan {
            $lockedPlan = $this->lockPlan($plan);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = TreatmentPlan::normalizeStatusValue($lockedPlan->status) ?? TreatmentPlan::DEFAULT_STATUS;

            $this->assertTransition($lockedPlan, TreatmentPlan::STATUS_COMPLETED);

            TreatmentPlan::runWithinManagedWorkflow(function () use ($lockedPlan, $resolvedActorId): void {
                $lockedPlan->forceFill([
                    'status' => TreatmentPlan::STATUS_COMPLETED,
                    'actual_end_date' => now()->toDateString(),
                    'updated_by' => $resolvedActorId,
                ])->save();

                $lockedPlan->updateProgress();
            });

            $lockedPlan->refresh();

            $this->recordAudit(
                plan: $lockedPlan,
                action: AuditLog::ACTION_COMPLETE,
                actorId: $resolvedActorId,
                metadata: [
                    'status_from' => $fromStatus,
                    'status_to' => TreatmentPlan::STATUS_COMPLETED,
                ],
            );

            return $lockedPlan;
        }, 3);
    }

    public function cancel(TreatmentPlan $plan, ?string $reason = null, ?int $actorId = null): TreatmentPlan
    {
        Gate::authorize('update', $plan);

        return $this->transition(
            plan: $plan,
            toStatus: TreatmentPlan::STATUS_CANCELLED,
            actorId: $actorId,
            attributes: [],
            auditAction: AuditLog::ACTION_CANCEL,
            metadata: [
                'reason' => filled($reason) ? trim((string) $reason) : null,
            ],
        );
    }

    protected function transition(
        TreatmentPlan $plan,
        string $toStatus,
        ?int $actorId,
        array $attributes,
        string $auditAction,
        array $metadata = [],
    ): TreatmentPlan {
        return DB::transaction(function () use ($plan, $toStatus, $actorId, $attributes, $auditAction, $metadata): TreatmentPlan {
            $lockedPlan = $this->lockPlan($plan);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = TreatmentPlan::normalizeStatusValue($lockedPlan->status) ?? TreatmentPlan::DEFAULT_STATUS;
            $normalizedTargetStatus = TreatmentPlan::normalizeStatusValue($toStatus) ?? $toStatus;

            if ($fromStatus === $normalizedTargetStatus) {
                return $lockedPlan;
            }

            $this->assertTransition($lockedPlan, $normalizedTargetStatus);

            TreatmentPlan::runWithinManagedWorkflow(function () use ($lockedPlan, $normalizedTargetStatus, $attributes, $resolvedActorId): void {
                $lockedPlan->forceFill(array_merge($attributes, [
                    'status' => $normalizedTargetStatus,
                    'updated_by' => $resolvedActorId,
                ]))->save();
            });

            $lockedPlan->refresh();

            $this->recordAudit(
                plan: $lockedPlan,
                action: $auditAction,
                actorId: $resolvedActorId,
                metadata: array_merge($metadata, [
                    'status_from' => $fromStatus,
                    'status_to' => $normalizedTargetStatus,
                ]),
            );

            return $lockedPlan;
        }, 3);
    }

    protected function lockPlan(TreatmentPlan $plan): TreatmentPlan
    {
        return TreatmentPlan::query()
            ->with(['patient:id,first_branch_id'])
            ->lockForUpdate()
            ->findOrFail($plan->getKey());
    }

    protected function assertTransition(TreatmentPlan $plan, string $toStatus): void
    {
        $fromStatus = TreatmentPlan::normalizeStatusValue($plan->status) ?? TreatmentPlan::DEFAULT_STATUS;

        if (TreatmentPlan::canTransitionStatus($fromStatus, $toStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Khong the chuyen ke hoach dieu tri tu "%s" sang "%s".',
                $plan->getStatusLabel(),
                TreatmentPlan::statusLabel($toStatus),
            ),
        ]);
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(TreatmentPlan $plan, string $action, ?int $actorId, array $metadata = []): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_PLAN,
            entityId: (int) $plan->getKey(),
            action: $action,
            actorId: $actorId,
            metadata: array_merge($metadata, [
                'treatment_plan_id' => (int) $plan->getKey(),
                'patient_id' => $plan->patient_id,
                'branch_id' => $plan->branch_id,
            ]),
        );
    }
}
