<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FactoryOrder;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FactoryOrderWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $data['status'] = FactoryOrder::STATUS_DRAFT;
        $data['ordered_at'] = null;
        $data['delivered_at'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(FactoryOrder $order, array $data): array
    {
        $incomingStatus = (string) ($data['status'] ?? $order->status);

        if ($incomingStatus !== (string) $order->status) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai lenh labo chi duoc thay doi qua FactoryOrderWorkflowService.',
            ]);
        }

        $data['status'] = $order->status;
        $data['ordered_at'] = $order->ordered_at;
        $data['delivered_at'] = $order->delivered_at;

        return $data;
    }

    public function markOrdered(FactoryOrder $order): FactoryOrder
    {
        Gate::authorize('transitionStatus', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_ORDERED,
            attributes: [
                'ordered_at' => $order->ordered_at ?? now(),
            ],
            auditAction: AuditLog::ACTION_UPDATE,
        );
    }

    public function markInProgress(FactoryOrder $order): FactoryOrder
    {
        Gate::authorize('transitionStatus', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_IN_PROGRESS,
            auditAction: AuditLog::ACTION_UPDATE,
        );
    }

    public function markDelivered(FactoryOrder $order): FactoryOrder
    {
        Gate::authorize('transitionStatus', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_DELIVERED,
            attributes: [
                'delivered_at' => now(),
            ],
            auditAction: AuditLog::ACTION_COMPLETE,
        );
    }

    public function cancel(FactoryOrder $order, ?string $reason = null): FactoryOrder
    {
        Gate::authorize('transitionStatus', $order);

        $resolvedReason = filled($reason) ? trim((string) $reason) : null;

        if ($resolvedReason === null) {
            throw ValidationException::withMessages([
                'notes' => 'Vui lòng ghi chú lý do hủy lệnh labo.',
            ]);
        }

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_CANCELLED,
            attributes: [
                'notes' => $resolvedReason,
            ],
            auditAction: AuditLog::ACTION_CANCEL,
            metadata: [
                'reason' => $resolvedReason,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    protected function transition(
        FactoryOrder $order,
        string $toStatus,
        array $attributes = [],
        ?string $auditAction = null,
        array $metadata = [],
    ): FactoryOrder {
        $resolvedActorId = $this->resolveActorId();

        return DB::transaction(function () use ($order, $toStatus, $attributes, $auditAction, $metadata, $resolvedActorId): FactoryOrder {
            $lockedOrder = FactoryOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $fromStatus = (string) ($lockedOrder->status ?? FactoryOrder::STATUS_DRAFT);

            if ($fromStatus === $toStatus) {
                return $lockedOrder;
            }

            if (! FactoryOrder::canTransitionStatus($fromStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf('Khong the chuyen trang thai lenh labo tu "%s" sang "%s".', $fromStatus, $toStatus),
                ]);
            }

            FactoryOrder::runWithinManagedWorkflow(function () use ($lockedOrder, $toStatus, $attributes): void {
                $lockedOrder->forceFill(array_merge($attributes, [
                    'status' => $toStatus,
                ]))->save();
            });

            $this->syncItemStatuses($lockedOrder, $toStatus);
            $lockedOrder->refresh();

            if ($auditAction !== null) {
                $this->recordAudit(
                    order: $lockedOrder,
                    action: $auditAction,
                    actorId: $resolvedActorId,
                    metadata: WorkflowAuditMetadata::transition(
                        fromStatus: $fromStatus,
                        toStatus: $toStatus,
                        reason: data_get($metadata, 'reason'),
                        metadata: $metadata,
                    ),
                );
            }

            return $lockedOrder->fresh(['items', 'supplier', 'patient', 'branch', 'doctor']);
        }, 3);
    }

    protected function syncItemStatuses(FactoryOrder $order, string $orderStatus): void
    {
        $itemStatus = match ($orderStatus) {
            FactoryOrder::STATUS_ORDERED => 'ordered',
            FactoryOrder::STATUS_IN_PROGRESS => 'in_progress',
            FactoryOrder::STATUS_DELIVERED => 'delivered',
            FactoryOrder::STATUS_CANCELLED => 'cancelled',
            default => null,
        };

        if ($itemStatus === null) {
            return;
        }

        $order->items()->update(['status' => $itemStatus]);
    }

    protected function resolveActorId(): ?int
    {
        return is_numeric(auth()->id()) ? (int) auth()->id() : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAudit(FactoryOrder $order, string $action, ?int $actorId, array $metadata = []): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_FACTORY_ORDER,
            entityId: (int) $order->getKey(),
            action: $action,
            actorId: $actorId,
            branchId: is_numeric($order->branch_id) ? (int) $order->branch_id : null,
            patientId: is_numeric($order->patient_id) ? (int) $order->patient_id : null,
            metadata: array_merge($metadata, [
                'factory_order_id' => (int) $order->getKey(),
                'order_no' => $order->order_no,
                'patient_id' => $order->patient_id,
                'branch_id' => $order->branch_id,
                'doctor_id' => $order->doctor_id,
                'supplier_id' => $order->supplier_id,
                'supplier_name' => $order->vendor_name,
            ]),
        );
    }
}
