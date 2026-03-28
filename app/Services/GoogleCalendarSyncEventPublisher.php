<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleCalendarSyncEvent;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GoogleCalendarSyncEventPublisher
{
    public function __construct(
        protected GoogleCalendarAppointmentPayloadBuilder $payloadBuilder,
    ) {}

    public function publishForAppointmentId(?int $appointmentId, ?string $eventType = null): ?GoogleCalendarSyncEvent
    {
        if (! $appointmentId) {
            return null;
        }

        $appointment = Appointment::query()->find($appointmentId);

        if (! $appointment) {
            return null;
        }

        return $this->publishForAppointment($appointment, $eventType);
    }

    public function publishForAppointment(Appointment $appointment, ?string $eventType = null): ?GoogleCalendarSyncEvent
    {
        if (! $this->shouldPushToGoogle()) {
            return null;
        }

        $resolvedEventType = $eventType ?? $this->resolveEventType($appointment);

        $payload = $resolvedEventType === GoogleCalendarSyncEvent::EVENT_DELETE
            ? [
                'appointment_id' => $appointment->id,
                'event_type' => GoogleCalendarSyncEvent::EVENT_DELETE,
            ]
            : $this->payloadBuilder->build($appointment);

        $checksum = $this->payloadBuilder->checksum($payload);
        $eventKey = $this->eventKey((int) $appointment->id, $resolvedEventType, $checksum);

        return DB::transaction(function () use ($appointment, $resolvedEventType, $payload, $checksum, $eventKey): GoogleCalendarSyncEvent {
            $existing = GoogleCalendarSyncEvent::query()
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->refreshExistingEvent(
                    event: $existing,
                    appointment: $appointment,
                    eventType: $resolvedEventType,
                    payload: $payload,
                    checksum: $checksum,
                );
            }

            try {
                return GoogleCalendarSyncEvent::query()->create([
                    'event_key' => $eventKey,
                    'appointment_id' => $appointment->id,
                    'branch_id' => $appointment->branch_id,
                    'event_type' => $resolvedEventType,
                    'payload' => $payload,
                    'payload_checksum' => $checksum,
                    'status' => GoogleCalendarSyncEvent::STATUS_PENDING,
                    'next_retry_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existing = GoogleCalendarSyncEvent::query()
                    ->where('event_key', $eventKey)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                return $this->refreshExistingEvent(
                    event: $existing,
                    appointment: $appointment,
                    eventType: $resolvedEventType,
                    payload: $payload,
                    checksum: $checksum,
                );
            }
        }, 3);
    }

    public function shouldPushToGoogle(): bool
    {
        return ClinicRuntimeSettings::isGoogleCalendarEnabled()
            && ClinicRuntimeSettings::googleCalendarAllowsPushToGoogle();
    }

    protected function resolveEventType(Appointment $appointment): string
    {
        if ($appointment->trashed()) {
            return GoogleCalendarSyncEvent::EVENT_DELETE;
        }

        if (in_array($appointment->status, [
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
            Appointment::STATUS_COMPLETED,
        ], true)) {
            return GoogleCalendarSyncEvent::EVENT_DELETE;
        }

        return GoogleCalendarSyncEvent::EVENT_UPSERT;
    }

    protected function eventKey(int $appointmentId, string $eventType, string $checksum): string
    {
        return substr(hash('sha1', implode('|', [
            'appointment',
            $appointmentId,
            $eventType,
            $checksum,
        ])), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function refreshExistingEvent(
        GoogleCalendarSyncEvent $event,
        Appointment $appointment,
        string $eventType,
        array $payload,
        string $checksum,
    ): GoogleCalendarSyncEvent {
        if ($event->status === GoogleCalendarSyncEvent::STATUS_PROCESSING) {
            return $event;
        }

        $event->resetForReplay([
            'appointment_id' => $appointment->id,
            'branch_id' => $appointment->branch_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'payload_checksum' => $checksum,
        ]);

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
