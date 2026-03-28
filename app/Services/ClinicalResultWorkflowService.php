<?php

namespace App\Services;

use App\Models\ClinicalResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalResultWorkflowService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function markPreliminary(
        ClinicalResult $result,
        ?array $payload = null,
        ?string $interpretation = null,
        ?string $notes = null,
        ?int $actorId = null,
        ?string $reason = null,
    ): ClinicalResult {
        return $this->transition(
            result: $result,
            toStatus: ClinicalResult::STATUS_PRELIMINARY,
            payload: $payload,
            interpretation: $interpretation,
            notes: $notes,
            actorId: $actorId,
            reason: $reason,
            trigger: 'manual_preliminary',
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function finalize(
        ClinicalResult $result,
        ?int $verifiedBy = null,
        ?array $payload = null,
        ?string $interpretation = null,
        ?string $notes = null,
        ?string $evidenceOverrideReason = null,
        ?int $actorId = null,
        ?string $reason = null,
    ): ClinicalResult {
        return $this->transition(
            result: $result,
            toStatus: ClinicalResult::STATUS_FINAL,
            payload: $payload,
            interpretation: $interpretation,
            notes: $notes,
            actorId: $actorId ?? $verifiedBy,
            reason: $reason,
            trigger: 'manual_finalize',
            attributes: array_filter([
                'verified_by' => $verifiedBy,
                'evidence_override_reason' => $evidenceOverrideReason,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function amend(
        ClinicalResult $result,
        ?array $payload = null,
        ?string $interpretation = null,
        ?string $notes = null,
        ?int $actorId = null,
        ?string $reason = null,
    ): ClinicalResult {
        return $this->transition(
            result: $result,
            toStatus: ClinicalResult::STATUS_AMENDED,
            payload: $payload,
            interpretation: $interpretation,
            notes: $notes,
            actorId: $actorId,
            reason: $reason,
            trigger: 'manual_amend',
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(
        ClinicalResult $result,
        string $toStatus,
        ?array $payload,
        ?string $interpretation,
        ?string $notes,
        ?int $actorId,
        ?string $reason,
        string $trigger,
        array $attributes = [],
    ): ClinicalResult {
        return DB::transaction(function () use ($result, $toStatus, $payload, $interpretation, $notes, $actorId, $reason, $trigger, $attributes): ClinicalResult {
            $lockedResult = ClinicalResult::query()
                ->lockForUpdate()
                ->findOrFail($result->getKey());

            $resolvedActorId = $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
            $fromStatus = (string) $lockedResult->status;

            if ($fromStatus === $toStatus) {
                return $lockedResult;
            }

            $this->assertTransition($lockedResult, $toStatus);

            $mutation = $attributes;

            if ($payload !== null) {
                $mutation['payload'] = $payload;
            }

            if ($interpretation !== null) {
                $mutation['interpretation'] = $interpretation;
            }

            if ($notes !== null) {
                $mutation['notes'] = $notes;
            }

            ClinicalResult::runWithinManagedWorkflow(function () use ($lockedResult, $toStatus, $mutation): void {
                $lockedResult->forceFill(array_merge($mutation, [
                    'status' => $toStatus,
                ]))->save();
            }, array_filter([
                'actor_id' => $resolvedActorId,
                'reason' => $reason,
                'trigger' => $trigger,
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedResult->fresh();
        }, 3);
    }

    protected function assertTransition(ClinicalResult $result, string $toStatus): void
    {
        if (ClinicalResult::canTransition((string) $result->status, $toStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'CLINICAL_RESULT_STATE_INVALID: Không thể chuyển trạng thái kết quả chỉ định.',
        ]);
    }
}
