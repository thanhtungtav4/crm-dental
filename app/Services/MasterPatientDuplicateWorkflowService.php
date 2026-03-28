<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MasterPatientDuplicate;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterPatientDuplicateWorkflowService
{
    public function resolve(
        MasterPatientDuplicate $duplicateCase,
        ?string $note = null,
        ?int $actorId = null,
        string $trigger = 'manual_resolve',
        bool $authorize = true,
    ): MasterPatientDuplicate {
        return $this->transition(
            duplicateCase: $duplicateCase,
            toStatus: MasterPatientDuplicate::STATUS_RESOLVED,
            attributes: [
                'review_note' => WorkflowAuditMetadata::normalizeReason($note),
            ],
            actorId: $actorId,
            reason: $note,
            trigger: $trigger,
            authorize: $authorize,
        );
    }

    public function ignore(
        MasterPatientDuplicate $duplicateCase,
        ?string $note = null,
        ?int $actorId = null,
        string $trigger = 'manual_ignore',
        bool $authorize = true,
    ): MasterPatientDuplicate {
        return $this->transition(
            duplicateCase: $duplicateCase,
            toStatus: MasterPatientDuplicate::STATUS_IGNORED,
            attributes: [
                'review_note' => WorkflowAuditMetadata::normalizeReason($note),
            ],
            actorId: $actorId,
            reason: $note,
            trigger: $trigger,
            authorize: $authorize,
        );
    }

    public function autoIgnore(
        MasterPatientDuplicate $duplicateCase,
        string $note,
        ?int $actorId = null,
        string $trigger = 'system_auto_ignore',
    ): MasterPatientDuplicate {
        return $this->ignore(
            duplicateCase: $duplicateCase,
            note: $note,
            actorId: $actorId,
            trigger: $trigger,
            authorize: false,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncStatus(
        MasterPatientDuplicate $duplicateCase,
        string $toStatus,
        array $attributes = [],
        ?int $actorId = null,
        ?string $reason = null,
        string $trigger = 'system_sync',
        ?string $auditAction = null,
    ): MasterPatientDuplicate {
        return $this->transition(
            duplicateCase: $duplicateCase,
            toStatus: $toStatus,
            attributes: $attributes,
            actorId: $actorId,
            reason: $reason,
            trigger: $trigger,
            authorize: false,
            auditAction: $auditAction,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(
        MasterPatientDuplicate $duplicateCase,
        string $toStatus,
        array $attributes = [],
        ?int $actorId = null,
        ?string $reason = null,
        string $trigger = 'manual_review',
        bool $authorize = true,
        ?string $auditAction = null,
    ): MasterPatientDuplicate {
        if ($authorize) {
            ActionGate::authorize(
                ActionPermission::MPI_DEDUPE_REVIEW,
                'Bạn không có quyền xử lý queue trùng bệnh nhân liên chi nhánh.',
            );
        }

        return DB::transaction(function () use ($duplicateCase, $toStatus, $attributes, $actorId, $reason, $trigger, $auditAction): MasterPatientDuplicate {
            $lockedDuplicateCase = $this->lockDuplicateCase($duplicateCase);
            $fromStatus = MasterPatientDuplicate::normalizeStatus((string) $lockedDuplicateCase->status)
                ?? MasterPatientDuplicate::STATUS_OPEN;
            $targetStatus = MasterPatientDuplicate::normalizeStatus($toStatus);

            if ($targetStatus === null) {
                throw ValidationException::withMessages([
                    'status' => 'MPI_DUPLICATE_STATE_INVALID: Trạng thái duplicate case không hợp lệ.',
                ]);
            }

            if (! MasterPatientDuplicate::canTransition($fromStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'MPI_DUPLICATE_STATE_INVALID: Không thể chuyển từ "%s" sang "%s".',
                        MasterPatientDuplicate::statusLabel($fromStatus),
                        MasterPatientDuplicate::statusLabel($targetStatus),
                    ),
                ]);
            }

            $resolvedActorId = $this->resolveActorId($actorId);
            $payload = array_merge($attributes, [
                'status' => $targetStatus,
            ]);

            if (in_array($targetStatus, [MasterPatientDuplicate::STATUS_RESOLVED, MasterPatientDuplicate::STATUS_IGNORED], true)) {
                if (! array_key_exists('reviewed_by', $payload)) {
                    $payload['reviewed_by'] = $resolvedActorId;
                }

                if (! array_key_exists('reviewed_at', $payload)) {
                    $payload['reviewed_at'] = now();
                }
            }

            MasterPatientDuplicate::runWithinManagedWorkflow(function () use ($lockedDuplicateCase, $payload): void {
                $lockedDuplicateCase->forceFill($payload)->save();
            }, array_filter([
                'actor_id' => $resolvedActorId,
                'reason' => WorkflowAuditMetadata::normalizeReason($reason),
                'trigger' => $trigger,
                'audit_action' => $auditAction
                    ?? ($targetStatus === MasterPatientDuplicate::STATUS_OPEN
                        ? AuditLog::ACTION_ROLLBACK
                        : AuditLog::ACTION_RESOLVE),
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedDuplicateCase->fresh();
        }, 3);
    }

    protected function lockDuplicateCase(MasterPatientDuplicate $duplicateCase): MasterPatientDuplicate
    {
        return MasterPatientDuplicate::query()
            ->whereKey($duplicateCase->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }
}
