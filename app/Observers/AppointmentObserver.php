<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Services\SyncAppointmentLifecycleSideEffects;

class AppointmentObserver
{
    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        if ($appointment->is_overbooked) {
            $this->logOverbookingChange($appointment);
        }

        SyncAppointmentLifecycleSideEffects::dispatch(
            (int) $appointment->id,
            SyncAppointmentLifecycleSideEffects::MODE_UPSERT,
            true,
            true,
        );
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        $statusChanged = $appointment->wasChanged('status');

        if ($statusChanged) {
            $this->logStatusChange($appointment);
        }

        if ($appointment->wasChanged(['is_overbooked', 'overbooking_reason'])) {
            $this->logOverbookingChange($appointment);
        }

        $shouldAttemptLeadConversion = $statusChanged
            && $appointment->status === Appointment::STATUS_COMPLETED;

        $shouldSyncOperationalArtifacts = $appointment->wasChanged([
            'status',
            'date',
            'duration_minutes',
            'assigned_to',
            'doctor_id',
            'patient_id',
            'branch_id',
            'note',
        ]);

        if ($shouldAttemptLeadConversion || $shouldSyncOperationalArtifacts) {
            SyncAppointmentLifecycleSideEffects::dispatch(
                (int) $appointment->id,
                SyncAppointmentLifecycleSideEffects::MODE_UPSERT,
                $statusChanged,
                $shouldSyncOperationalArtifacts,
                $shouldAttemptLeadConversion,
            );
        }
    }

    protected function logStatusChange(Appointment $appointment): void
    {
        $actorId = auth()->id();

        if (! $actorId) {
            return;
        }

        $action = match ($appointment->status) {
            Appointment::STATUS_CANCELLED => AuditLog::ACTION_CANCEL,
            Appointment::STATUS_RESCHEDULED => AuditLog::ACTION_RESCHEDULE,
            Appointment::STATUS_NO_SHOW => AuditLog::ACTION_NO_SHOW,
            Appointment::STATUS_COMPLETED => AuditLog::ACTION_COMPLETE,
            default => null,
        };

        if ($action === null) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_APPOINTMENT,
            entityId: $appointment->id,
            action: $action,
            actorId: $actorId,
            metadata: [
                'patient_id' => $appointment->patient_id,
                'customer_id' => $appointment->customer_id,
                'status_from' => Appointment::normalizeStatus((string) $appointment->getOriginal('status'))
                    ?? Appointment::DEFAULT_STATUS,
                'status_to' => $appointment->status,
                'appointment_at' => $appointment->date?->toDateTimeString(),
                'doctor_id' => $appointment->doctor_id,
                'branch_id' => $appointment->branch_id,
                'cancellation_reason' => $appointment->cancellation_reason,
                'reschedule_reason' => $appointment->reschedule_reason,
            ]
        );
    }

    protected function logOverbookingChange(Appointment $appointment): void
    {
        $actorId = auth()->id();

        if (! $actorId) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_APPOINTMENT,
            entityId: $appointment->id,
            action: AuditLog::ACTION_UPDATE,
            actorId: $actorId,
            metadata: [
                'patient_id' => $appointment->patient_id,
                'customer_id' => $appointment->customer_id,
                'is_overbooked' => (bool) $appointment->is_overbooked,
                'overbooking_reason' => $appointment->overbooking_reason,
                'doctor_id' => $appointment->doctor_id,
                'branch_id' => $appointment->branch_id,
                'appointment_at' => $appointment->date?->toDateTimeString(),
            ],
        );
    }

    /**
     * Handle the Appointment "deleted" event.
     */
    public function deleted(Appointment $appointment): void
    {
        SyncAppointmentLifecycleSideEffects::dispatch((int) $appointment->id, SyncAppointmentLifecycleSideEffects::MODE_DELETED);
    }

    /**
     * Handle the Appointment "restored" event.
     */
    public function restored(Appointment $appointment): void
    {
        SyncAppointmentLifecycleSideEffects::dispatch((int) $appointment->id, SyncAppointmentLifecycleSideEffects::MODE_RESTORED);
    }

    /**
     * Handle the Appointment "force deleted" event.
     */
    public function forceDeleted(Appointment $appointment): void
    {
        //
    }
}
