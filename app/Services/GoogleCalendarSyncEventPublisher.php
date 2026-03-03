<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleCalendarSyncEvent;
use App\Support\ClinicRuntimeSettings;

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

        return GoogleCalendarSyncEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id,
                'event_type' => $resolvedEventType,
                'payload' => $payload,
                'payload_checksum' => $checksum,
                'status' => GoogleCalendarSyncEvent::STATUS_PENDING,
                'next_retry_at' => now(),
            ],
        );
    }

    public function shouldPushToGoogle(): bool
    {
        if (! ClinicRuntimeSettings::isGoogleCalendarEnabled()) {
            return false;
        }

        $mode = ClinicRuntimeSettings::googleCalendarSyncMode();

        return in_array($mode, ['two_way', 'one_way_to_google'], true);
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
}
