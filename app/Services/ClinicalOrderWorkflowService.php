<?php

namespace App\Services;

use App\Models\ClinicalOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalOrderWorkflowService
{
    public function markInProgress(
        ClinicalOrder $order,
        ?int $actorId = null,
        ?string $reason = null,
        string $trigger = 'manual_in_progress',
    ): ClinicalOrder {
        return $this->transition(
            order: $order,
            toStatus: ClinicalOrder::STATUS_IN_PROGRESS,
            actorId: $actorId,
            reason: $reason,
            trigger: $trigger,
        );
    }

    public function markCompleted(
        ClinicalOrder $order,
        ?int $actorId = null,
        ?string $reason = null,
        string $trigger = 'manual_complete',
    ): ClinicalOrder {
        return $this->transition(
            order: $order,
            toStatus: ClinicalOrder::STATUS_COMPLETED,
            actorId: $actorId,
            reason: $reason,
            trigger: $trigger,
        );
    }

    public function cancel(ClinicalOrder $order, ?string $reason = null, ?int $actorId = null): ClinicalOrder
    {
        return $this->transition(
            order: $order,
            toStatus: ClinicalOrder::STATUS_CANCELLED,
            actorId: $actorId,
            reason: $reason,
            trigger: 'manual_cancel',
            attributes: [
                'notes' => filled($reason)
                    ? trim((string) (($order->notes ? $order->notes."\n" : '').$reason))
                    : $order->notes,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(
        ClinicalOrder $order,
        string $toStatus,
        ?int $actorId,
        ?string $reason,
        string $trigger,
        array $attributes = [],
    ): ClinicalOrder {
        return DB::transaction(function () use ($order, $toStatus, $actorId, $reason, $trigger, $attributes): ClinicalOrder {
            $lockedOrder = ClinicalOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $fromStatus = (string) $lockedOrder->status;
            $resolvedActorId = $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);

            if ($fromStatus === $toStatus) {
                return $lockedOrder;
            }

            $this->assertTransition($lockedOrder, $toStatus);

            ClinicalOrder::runWithinManagedWorkflow(function () use ($lockedOrder, $toStatus, $attributes): void {
                $lockedOrder->forceFill(array_merge($attributes, [
                    'status' => $toStatus,
                ]))->save();
            }, array_filter([
                'actor_id' => $resolvedActorId,
                'reason' => $reason,
                'trigger' => $trigger,
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedOrder->fresh();
        }, 3);
    }

    protected function assertTransition(ClinicalOrder $order, string $toStatus): void
    {
        if (ClinicalOrder::canTransition((string) $order->status, $toStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'CLINICAL_ORDER_STATE_INVALID: Không thể chuyển trạng thái chỉ định.',
        ]);
    }
}
