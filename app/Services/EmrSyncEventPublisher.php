<?php

namespace App\Services;

use App\Models\EmrAuditLog;
use App\Models\EmrSyncEvent;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;

class EmrSyncEventPublisher
{
    public function __construct(
        protected EmrPatientPayloadBuilder $payloadBuilder,
        protected EmrAuditLogger $emrAuditLogger,
    ) {}

    public function publishForPatientId(?int $patientId, string $eventType): ?EmrSyncEvent
    {
        if (! $patientId) {
            return null;
        }

        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            return null;
        }

        return $this->publishForPatient($patient, $eventType);
    }

    public function publishForPatient(Patient $patient, string $eventType): ?EmrSyncEvent
    {
        if (! ClinicRuntimeSettings::isEmrEnabled()) {
            return null;
        }

        $payload = $this->payloadBuilder->build($patient);
        $checksum = $this->payloadBuilder->checksum($payload);
        $eventKey = $this->eventKey($patient->id, $eventType, $checksum);

        $event = EmrSyncEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'event_type' => $eventType,
                'payload' => $payload,
                'payload_checksum' => $checksum,
                'status' => EmrSyncEvent::STATUS_PENDING,
                'next_retry_at' => now(),
            ],
        );

        $this->emrAuditLogger->recordSyncEvent(
            event: $event,
            action: $event->wasRecentlyCreated ? EmrAuditLog::ACTION_PUBLISH : EmrAuditLog::ACTION_DEDUPE,
            context: [
                'event_key' => $event->event_key,
                'event_type' => $event->event_type,
                'payload_checksum' => $event->payload_checksum,
            ],
            actorId: auth()->id(),
        );

        return $event;
    }

    protected function eventKey(int $patientId, string $eventType, string $checksum): string
    {
        return substr(hash('sha1', implode('|', [
            'patient',
            $patientId,
            $eventType,
            $checksum,
        ])), 0, 40);
    }
}
