<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;

class PatientAppointmentActionReadModelService
{
    public function hasActiveAppointments(Patient $patient): bool
    {
        return $this->activeAppointmentsQuery($patient)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function activeAppointmentOptions(Patient $patient): array
    {
        return $this->activeAppointmentsQuery($patient)
            ->with('doctor:id,name')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function (Appointment $appointment): array {
                $label = sprintf(
                    '%s — %s — %s',
                    $appointment->date?->format('d/m/Y H:i') ?? '-',
                    $appointment->doctor?->name ?? 'Chưa chọn bác sĩ',
                    Appointment::statusLabel($appointment->status),
                );

                return [(int) $appointment->id => $label];
            })
            ->all();
    }

    protected function activeAppointmentsQuery(Patient $patient): Builder
    {
        return Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
            ->where('date', '>=', now());
    }
}
