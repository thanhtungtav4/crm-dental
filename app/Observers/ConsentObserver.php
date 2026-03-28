<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Support\WorkflowAuditMetadata;

class ConsentObserver
{
    public function created(Consent $consent): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_CONSENT,
            entityId: $consent->id,
            action: AuditLog::ACTION_CREATE,
            actorId: auth()->id() ?? $consent->signed_by,
            branchId: $consent->resolveBranchId(),
            patientId: $consent->patient_id,
            metadata: array_merge($this->buildMetadata($consent), [
                'status_to' => $consent->status,
            ]),
        );
    }

    public function updated(Consent $consent): void
    {
        if (! $consent->wasChanged('status')) {
            return;
        }

        $action = match ($consent->status) {
            Consent::STATUS_SIGNED => AuditLog::ACTION_APPROVE,
            Consent::STATUS_REVOKED => AuditLog::ACTION_CANCEL,
            Consent::STATUS_EXPIRED => AuditLog::ACTION_FAIL,
            default => AuditLog::ACTION_UPDATE,
        };

        AuditLog::record(
            entityType: AuditLog::ENTITY_CONSENT,
            entityId: $consent->id,
            action: $action,
            actorId: auth()->id() ?? $consent->signed_by,
            branchId: $consent->resolveBranchId(),
            patientId: $consent->patient_id,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: (string) ($consent->getOriginal('status') ?: Consent::STATUS_PENDING),
                toStatus: $consent->status,
                metadata: $this->buildMetadata($consent),
            ),
        );
    }

    protected function buildMetadata(Consent $consent): array
    {
        return [
            'patient_id' => $consent->patient_id,
            'branch_id' => $consent->resolveBranchId(),
            'service_id' => $consent->service_id,
            'plan_item_id' => $consent->plan_item_id,
            'consent_type' => $consent->consent_type,
            'consent_version' => $consent->consent_version,
            'signed_by' => $consent->signed_by,
            'signed_at' => $consent->signed_at?->toDateTimeString(),
            'revoked_at' => $consent->revoked_at?->toDateTimeString(),
            'expires_at' => $consent->expires_at?->toDateTimeString(),
            'signature_context' => $consent->signature_context,
        ];
    }
}
