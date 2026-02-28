<?php

namespace App\Services;

use App\Models\EmrAuditLog;
use App\Models\EmrSyncEvent;
use Carbon\CarbonInterface;

class EmrAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?int $patientId,
        ?int $visitEpisodeId,
        ?int $branchId,
        ?int $actorId = null,
        array $context = [],
        ?CarbonInterface $occurredAt = null,
    ): EmrAuditLog {
        return EmrAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'patient_id' => $patientId,
            'visit_episode_id' => $visitEpisodeId,
            'branch_id' => $branchId,
            'actor_id' => $actorId,
            'context' => $context,
            'occurred_at' => $occurredAt?->toDateTimeString() ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSyncEvent(
        EmrSyncEvent $event,
        string $action,
        array $context = [],
        ?int $actorId = null,
    ): EmrAuditLog {
        return $this->record(
            entityType: EmrAuditLog::ENTITY_SYNC_EVENT,
            entityId: (int) $event->id,
            action: $action,
            patientId: $event->patient_id ? (int) $event->patient_id : null,
            visitEpisodeId: null,
            branchId: $event->branch_id ? (int) $event->branch_id : null,
            actorId: $actorId,
            context: $context,
        );
    }
}
