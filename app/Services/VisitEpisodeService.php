<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\VisitEpisode;

class VisitEpisodeService
{
    public function syncFromAppointment(Appointment $appointment, bool $statusChanged = false): void
    {
        $appointmentStatus = Appointment::normalizeStatus($appointment->status) ?? Appointment::DEFAULT_STATUS;

        $episode = VisitEpisode::withTrashed()->firstOrNew([
            'appointment_id' => $appointment->id,
        ]);

        if ($episode->trashed()) {
            $episode->restore();
        }

        $episode->patient_id = $appointment->patient_id;
        $episode->doctor_id = $appointment->doctor_id;
        $episode->branch_id = $appointment->branch_id;
        $episode->scheduled_at = $appointment->date;
        $episode->planned_duration_minutes = $appointment->duration_minutes;
        $episode->status = $this->mapVisitStatus($appointmentStatus);
        $episode->notes = $appointment->note;

        if ($statusChanged || ! $episode->exists) {
            $this->applyStatusMilestones($episode, $appointmentStatus, $appointment);
        }

        $this->recalculateDurations($episode);
        $episode->save();
    }

    public function markAppointmentDeleted(Appointment $appointment): void
    {
        $episode = VisitEpisode::query()
            ->where('appointment_id', $appointment->id)
            ->first();

        if (! $episode) {
            return;
        }

        if ($episode->status !== VisitEpisode::STATUS_COMPLETED) {
            $episode->status = VisitEpisode::STATUS_CANCELLED;
        }

        if ($episode->in_chair_at && ! $episode->check_out_at) {
            $episode->check_out_at = now();
        }

        $this->recalculateDurations($episode);
        $episode->save();
    }

    protected function applyStatusMilestones(VisitEpisode $episode, string $appointmentStatus, Appointment $appointment): void
    {
        if ($appointmentStatus === Appointment::STATUS_CONFIRMED && ! $episode->check_in_at) {
            $episode->check_in_at = $appointment->confirmed_at ?: now();
        }

        if ($appointmentStatus === Appointment::STATUS_IN_PROGRESS) {
            $inferredCheckIn = $episode->check_in_at ?: ($appointment->confirmed_at ?: now());
            $episode->check_in_at = $inferredCheckIn;
            $episode->arrived_at = $episode->arrived_at ?: now();
            $episode->in_chair_at = $episode->in_chair_at ?: now();
        }

        if ($appointmentStatus === Appointment::STATUS_COMPLETED) {
            $inferredCheckIn = $episode->check_in_at ?: ($appointment->confirmed_at ?: now());
            $episode->check_in_at = $inferredCheckIn;
            $episode->arrived_at = $episode->arrived_at ?: $inferredCheckIn;
            $episode->in_chair_at = $episode->in_chair_at ?: $episode->arrived_at;
            $episode->check_out_at = $episode->check_out_at ?: now();
        }

        if (in_array($appointmentStatus, [Appointment::STATUS_CANCELLED, Appointment::STATUS_RESCHEDULED], true)) {
            if ($episode->in_chair_at && ! $episode->check_out_at) {
                $episode->check_out_at = now();
            }
        }
    }

    protected function recalculateDurations(VisitEpisode $episode): void
    {
        $waitingMinutes = null;
        if ($episode->check_in_at && $episode->in_chair_at) {
            $waitingMinutes = max($episode->check_in_at->diffInMinutes($episode->in_chair_at, false), 0);
        }

        $chairMinutes = null;
        if ($episode->in_chair_at && $episode->check_out_at) {
            $chairMinutes = max($episode->in_chair_at->diffInMinutes($episode->check_out_at, false), 0);
        }

        $actualDuration = $chairMinutes;
        if ($actualDuration === null && $episode->check_in_at && $episode->check_out_at) {
            $actualDuration = max($episode->check_in_at->diffInMinutes($episode->check_out_at, false), 0);
        }

        $overrunMinutes = null;
        if ($actualDuration !== null && $episode->planned_duration_minutes !== null) {
            $overrunMinutes = max($actualDuration - $episode->planned_duration_minutes, 0);
        }

        $episode->waiting_minutes = $waitingMinutes;
        $episode->chair_minutes = $chairMinutes;
        $episode->actual_duration_minutes = $actualDuration;
        $episode->overrun_minutes = $overrunMinutes;
    }

    protected function mapVisitStatus(string $appointmentStatus): string
    {
        return match ($appointmentStatus) {
            Appointment::STATUS_IN_PROGRESS => VisitEpisode::STATUS_IN_PROGRESS,
            Appointment::STATUS_COMPLETED => VisitEpisode::STATUS_COMPLETED,
            Appointment::STATUS_NO_SHOW => VisitEpisode::STATUS_NO_SHOW,
            Appointment::STATUS_CANCELLED => VisitEpisode::STATUS_CANCELLED,
            Appointment::STATUS_RESCHEDULED => VisitEpisode::STATUS_RESCHEDULED,
            default => VisitEpisode::STATUS_SCHEDULED,
        };
    }
}
