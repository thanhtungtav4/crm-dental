<?php

namespace App\Services;

use App\Models\EmrAuditLog;
use App\Models\EmrSyncEvent;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
        $eventKey = $this->eventKey((int) $patient->id, $eventType, $checksum);
        $event = DB::transaction(function () use ($patient, $eventType, $payload, $checksum, $eventKey): EmrSyncEvent {
            $existing = EmrSyncEvent::query()
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->refreshExistingEvent(
                    event: $existing,
                    patient: $patient,
                    eventType: $eventType,
                    payload: $payload,
                    checksum: $checksum,
                );
            }

            try {
                return EmrSyncEvent::query()->create([
                    'event_key' => $eventKey,
                    'patient_id' => $patient->id,
                    'branch_id' => $patient->first_branch_id,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'payload_checksum' => $checksum,
                    'status' => EmrSyncEvent::STATUS_PENDING,
                    'next_retry_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existing = EmrSyncEvent::query()
                    ->where('event_key', $eventKey)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                return $this->refreshExistingEvent(
                    event: $existing,
                    patient: $patient,
                    eventType: $eventType,
                    payload: $payload,
                    checksum: $checksum,
                );
            }
        }, 3);

        if (! $event->wasRecentlyCreated) {
            $this->emrAuditLogger->recordSyncEvent(
                event: $event,
                action: EmrAuditLog::ACTION_DEDUPE,
                context: [
                    'event_key' => $event->event_key,
                    'event_type' => $event->event_type,
                    'payload_checksum' => $event->payload_checksum,
                ],
                actorId: auth()->id(),
            );

            return $event;
        }

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

    protected function eventKey(int $patientId, string $eventType, string $checksum): string
    {
        return substr(hash('sha1', implode('|', [
            'patient',
            $patientId,
            $eventType,
            $checksum,
        ])), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function refreshExistingEvent(
        EmrSyncEvent $event,
        Patient $patient,
        string $eventType,
        array $payload,
        string $checksum,
    ): EmrSyncEvent {
        if ($event->status === EmrSyncEvent::STATUS_PROCESSING) {
            return $event;
        }

        $event->forceFill([
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'payload_checksum' => $checksum,
            'status' => EmrSyncEvent::STATUS_PENDING,
            'attempts' => 0,
            'next_retry_at' => now(),
            'locked_at' => null,
            'processed_at' => null,
            'last_http_status' => null,
            'last_error' => null,
            'external_patient_id' => null,
        ])->save();

        return $event->fresh() ?? $event;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['23000', '23505'], true)
            || $driverCode === 1062;
    }
}
