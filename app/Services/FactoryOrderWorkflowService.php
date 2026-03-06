<?php

namespace App\Services;

use App\Models\FactoryOrder;
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
        Gate::authorize('view', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_ORDERED,
            attributes: [
                'ordered_at' => $order->ordered_at ?? now(),
            ],
        );
    }

    public function markInProgress(FactoryOrder $order): FactoryOrder
    {
        Gate::authorize('view', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_IN_PROGRESS,
        );
    }

    public function markDelivered(FactoryOrder $order): FactoryOrder
    {
        Gate::authorize('view', $order);

        return $this->transition(
            order: $order,
            toStatus: FactoryOrder::STATUS_DELIVERED,
            attributes: [
                'delivered_at' => now(),
            ],
        );
    }

    public function cancel(FactoryOrder $order, ?string $reason = null): FactoryOrder
    {
        Gate::authorize('view', $order);

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
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(FactoryOrder $order, string $toStatus, array $attributes = []): FactoryOrder
    {
        return DB::transaction(function () use ($order, $toStatus, $attributes): FactoryOrder {
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

            $this->syncItemStatuses($lockedOrder->fresh('items'), $toStatus);

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
}
