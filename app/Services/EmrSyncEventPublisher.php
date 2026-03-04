<?php

namespace App\Services;

use App\Models\EmrAuditLog;
use App\Models\EmrSyncEvent;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Str;

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
        $openDuplicateEvent = $this->findOpenDuplicateEvent(
            patientId: (int) $patient->id,
            eventType: $eventType,
            payloadChecksum: $checksum,
        );

        if ($openDuplicateEvent) {
            $this->emrAuditLogger->recordSyncEvent(
                event: $openDuplicateEvent,
                action: EmrAuditLog::ACTION_DEDUPE,
                context: [
                    'event_key' => $openDuplicateEvent->event_key,
                    'event_type' => $openDuplicateEvent->event_type,
                    'payload_checksum' => $openDuplicateEvent->payload_checksum,
                ],
                actorId: auth()->id(),
            );

            return $openDuplicateEvent;
        }

        $event = EmrSyncEvent::query()->create([
            'event_key' => $this->eventKey($patient->id, $eventType, $checksum),
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'payload_checksum' => $checksum,
            'status' => EmrSyncEvent::STATUS_PENDING,
            'next_retry_at' => now(),
        ]);

        $this->emrAuditLogger->recordSyncEvent(
            event: $event,
            action: EmrAuditLog::ACTION_PUBLISH,
            context: [
                'event_key' => $event->event_key,
                'event_type' => $event->event_type,
                'payload_checksum' => $event->payload_checksum,
            ],
            actorId: auth()->id(),
        );

        return $event;
    }

    protected function findOpenDuplicateEvent(int $patientId, string $eventType, string $payloadChecksum): ?EmrSyncEvent
    {
        return EmrSyncEvent::query()
            ->where('patient_id', $patientId)
            ->where('event_type', $eventType)
            ->where('payload_checksum', $payloadChecksum)
            ->whereIn('status', [
                EmrSyncEvent::STATUS_PENDING,
                EmrSyncEvent::STATUS_PROCESSING,
                EmrSyncEvent::STATUS_FAILED,
            ])
            ->latest('id')
            ->first();
    }

    protected function eventKey(int $patientId, string $eventType, string $checksum): string
    {
        return substr(hash('sha1', implode('|', [
            'patient',
            $patientId,
            $eventType,
            $checksum,
            now()->format('Uv'),
            (string) Str::uuid(),
        ])), 0, 40);
    }
}
