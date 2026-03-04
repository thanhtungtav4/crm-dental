<?php

namespace App\Services;

use App\Models\Appointment;

class GoogleCalendarAppointmentPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Appointment $appointment): array
    {
        $appointment->loadMissing([
            'patient:id,full_name,phone,email',
            'doctor:id,name',
            'branch:id,name',
        ]);

        $startAt = $appointment->date?->copy()->setTimezone(config('app.timezone'));
        $durationMinutes = max(1, (int) ($appointment->duration_minutes ?? 30));
        $endAt = $startAt?->copy()->addMinutes($durationMinutes);

        $patientName = trim((string) $appointment->patient?->full_name);
        $doctorName = trim((string) $appointment->doctor?->name);
        $branchName = trim((string) $appointment->branch?->name);
        $statusLabel = Appointment::statusLabel($appointment->status);

        $summary = trim(sprintf(
            '[%s] %s',
            $statusLabel,
            $patientName !== '' ? $patientName : 'Lịch hẹn bệnh nhân',
        ));

        $descriptionLines = array_filter([
            'Mã lịch hẹn CRM: #'.$appointment->id,
            $patientName !== '' ? 'Bệnh nhân: '.$patientName : null,
            $doctorName !== '' ? 'Bác sĩ: '.$doctorName : null,
            $branchName !== '' ? 'Chi nhánh: '.$branchName : null,
            'Trạng thái: '.$statusLabel,
            filled($appointment->note) ? 'Ghi chú: '.trim((string) $appointment->note) : null,
        ]);

        return [
            'summary' => $summary,
            'description' => implode("\n", $descriptionLines),
            'start' => [
                'dateTime' => $startAt?->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $endAt?->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'extendedProperties' => [
                'private' => [
                    'crm_appointment_id' => (string) $appointment->id,
                    'crm_branch_id' => (string) ($appointment->branch_id ?? ''),
                    'crm_status' => (string) $appointment->status,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checksum(array $payload): string
    {
        return hash('sha1', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
