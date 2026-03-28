<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\MaterialIssueNote;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialIssueNoteWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $data['status'] = MaterialIssueNote::STATUS_DRAFT;
        $data['posted_at'] = null;
        $data['posted_by'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(MaterialIssueNote $note, array $data): array
    {
        $incomingStatus = (string) ($data['status'] ?? $note->status);
        $currentStatus = (string) $note->status;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai phieu xuat chi duoc thay doi qua MaterialIssueNoteWorkflowService.',
            ]);
        }

        $data['status'] = $note->status;
        $data['posted_at'] = $note->posted_at;
        $data['posted_by'] = $note->posted_by;

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public function post(MaterialIssueNote $note, ?string $reason = null, ?int $actorId = null): array
    {
        $lowStockWarnings = [];

        DB::transaction(function () use (&$lowStockWarnings, $note, $reason, $actorId): void {
            $lockedNote = $this->lockNote($note);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = (string) $lockedNote->status;

            if ($fromStatus === MaterialIssueNote::STATUS_POSTED) {
                return;
            }

            if ($fromStatus !== MaterialIssueNote::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ phiếu nháp mới được xuất kho.',
                ]);
            }

            $items = $lockedNote->items()
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Phiếu xuất chưa có vật tư.',
                ]);
            }

            foreach ($items as $item) {
                $noteBranchId = is_numeric($lockedNote->branch_id) ? (int) $lockedNote->branch_id : null;
                $quantity = (int) $item->quantity;

                $mutation = app(InventoryMutationService::class)->consumeBatch(
                    materialId: (int) $item->material_id,
                    batchId: (int) $item->material_batch_id,
                    quantity: $quantity,
                    expectedBranchId: $noteBranchId,
                    branchMismatchMessage: 'Vat tu khong thuoc chi nhanh cua phieu xuat.',
                );

                $material = $mutation['material'];
                $materialBatch = $mutation['batch'];

                if ($material->isLowStock()) {
                    $lowStockWarnings[] = sprintf(
                        '%s (tồn: %d, min: %d)',
                        (string) $material->name,
                        (int) ($material->stock_qty ?? 0),
                        (int) ($material->min_stock ?? 0),
                    );
                }

                InventoryTransaction::query()->create([
                    'material_id' => $material->id,
                    'material_batch_id' => $materialBatch->id,
                    'branch_id' => $lockedNote->branch_id,
                    'material_issue_note_id' => $lockedNote->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'note' => 'Xuất theo phiếu: '.$lockedNote->note_no,
                    'created_by' => $resolvedActorId,
                ]);
            }

            MaterialIssueNote::runWithinManagedWorkflow(function () use ($lockedNote, $resolvedActorId): void {
                $lockedNote->forceFill([
                    'status' => MaterialIssueNote::STATUS_POSTED,
                    'posted_at' => now(),
                    'posted_by' => $resolvedActorId,
                ])->save();
            });

            $lockedNote->refresh();

            AuditLog::record(
                entityType: AuditLog::ENTITY_MATERIAL_ISSUE_NOTE,
                entityId: (int) $lockedNote->getKey(),
                action: AuditLog::ACTION_COMPLETE,
                actorId: $resolvedActorId,
                metadata: WorkflowAuditMetadata::transition(
                    fromStatus: $fromStatus,
                    toStatus: MaterialIssueNote::STATUS_POSTED,
                    reason: $reason,
                    metadata: [
                        'trigger' => 'manual_post',
                        'material_issue_note_id' => (int) $lockedNote->getKey(),
                        'note_no' => $lockedNote->note_no,
                        'patient_id' => $lockedNote->patient_id,
                        'branch_id' => $lockedNote->branch_id,
                        'item_count' => $items->count(),
                        'posted_at' => optional($lockedNote->posted_at)?->toIso8601String(),
                        'posted_by' => $lockedNote->posted_by,
                    ],
                ),
            );
        }, 3);

        $note->refresh();

        return array_values(array_unique($lowStockWarnings));
    }

    public function cancel(MaterialIssueNote $note, ?string $reason = null, ?int $actorId = null): MaterialIssueNote
    {
        return DB::transaction(function () use ($note, $reason, $actorId): MaterialIssueNote {
            $lockedNote = $this->lockNote($note);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = (string) $lockedNote->status;

            if ($fromStatus === MaterialIssueNote::STATUS_CANCELLED) {
                return $lockedNote;
            }

            if ($fromStatus !== MaterialIssueNote::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ phiếu nháp mới được hủy.',
                ]);
            }

            MaterialIssueNote::runWithinManagedWorkflow(function () use ($lockedNote): void {
                $lockedNote->forceFill([
                    'status' => MaterialIssueNote::STATUS_CANCELLED,
                    'posted_at' => null,
                    'posted_by' => null,
                ])->save();
            });

            $lockedNote->refresh();

            AuditLog::record(
                entityType: AuditLog::ENTITY_MATERIAL_ISSUE_NOTE,
                entityId: (int) $lockedNote->getKey(),
                action: AuditLog::ACTION_CANCEL,
                actorId: $resolvedActorId,
                metadata: WorkflowAuditMetadata::transition(
                    fromStatus: $fromStatus,
                    toStatus: MaterialIssueNote::STATUS_CANCELLED,
                    reason: $reason,
                    metadata: [
                        'trigger' => 'manual_cancel',
                        'material_issue_note_id' => (int) $lockedNote->getKey(),
                        'note_no' => $lockedNote->note_no,
                        'patient_id' => $lockedNote->patient_id,
                        'branch_id' => $lockedNote->branch_id,
                        'item_count' => $lockedNote->items()->count(),
                    ],
                ),
            );

            return $lockedNote;
        }, 3);
    }

    protected function lockNote(MaterialIssueNote $note): MaterialIssueNote
    {
        return MaterialIssueNote::query()
            ->whereKey($note->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }
}
