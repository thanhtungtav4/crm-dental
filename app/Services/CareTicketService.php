<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentSession;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;

class CareTicketService
{
    public function syncAppointment(Appointment $appointment): void
    {
        if (! $appointment->patient_id) {
            return;
        }

        if ($appointment->status !== 'no_show') {
            $this->cancelTicket(Appointment::class, $appointment->id, 'appointment_reminder');
            return;
        }

        $careAt = $appointment->date ?? now();
        $assigneeId = $appointment->assigned_to ?: $appointment->doctor_id;
        $content = $appointment->note ?: 'Nhắc lịch hẹn sau khi bệnh nhân không đến.';

        $this->upsertTicket([
            'patient_id' => $appointment->patient_id,
            'customer_id' => $appointment->customer_id ?? $this->resolveCustomerId($appointment->patient_id),
            'user_id' => $assigneeId,
            'type' => 'general',
            'care_type' => 'appointment_reminder',
            'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
            'care_status' => 'planned',
            'care_at' => $careAt,
            'content' => $content,
        ], Appointment::class, $appointment->id);
    }

    public function syncPrescription(Prescription $prescription): void
    {
        if (! $prescription->patient_id) {
            return;
        }

        $baseDate = $prescription->treatment_date
            ? Carbon::parse($prescription->treatment_date)
            : $prescription->created_at;

        $offsetDays = ClinicRuntimeSettings::medicationReminderOffsetDays();
        $careAt = $baseDate ? $baseDate->copy()->startOfDay()->addDays($offsetDays) : now();
        $assigneeId = $prescription->doctor_id ?: $prescription->created_by;
        $content = $prescription->notes ?: ($prescription->prescription_name ? 'Nhắc uống thuốc: ' . $prescription->prescription_name : 'Nhắc lịch uống thuốc');

        $this->upsertTicket([
            'patient_id' => $prescription->patient_id,
            'customer_id' => $this->resolveCustomerId($prescription->patient_id),
            'user_id' => $assigneeId,
            'type' => 'general',
            'care_type' => 'medication_reminder',
            'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
            'care_status' => 'planned',
            'care_at' => $careAt,
            'content' => $content,
        ], Prescription::class, $prescription->id);
    }

    public function syncTreatmentSession(TreatmentSession $session): void
    {
        $patientId = $session->treatmentPlan?->patient_id;
        if (! $patientId) {
            return;
        }

        $isCompleted = in_array($session->status, ['done', 'completed', null], true);
        if (! $session->performed_at || ! $isCompleted) {
            $this->cancelTicket(TreatmentSession::class, $session->id, 'post_treatment_follow_up');
            return;
        }

        $offsetDays = ClinicRuntimeSettings::postTreatmentFollowUpOffsetDays();
        $careAt = $session->performed_at->copy()->addDays($offsetDays);
        $assigneeId = $session->doctor_id ?: $session->assistant_id;
        $content = $session->notes ?: 'Hỏi thăm sau điều trị';

        $this->upsertTicket([
            'patient_id' => $patientId,
            'customer_id' => $this->resolveCustomerId($patientId),
            'user_id' => $assigneeId,
            'type' => 'general',
            'care_type' => 'post_treatment_follow_up',
            'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
            'care_status' => 'planned',
            'care_at' => $careAt,
            'content' => $content,
        ], TreatmentSession::class, $session->id);
    }

    protected function upsertTicket(array $attributes, string $sourceType, int $sourceId): void
    {
        $attributes['source_type'] = $sourceType;
        $attributes['source_id'] = $sourceId;

        $note = Note::firstOrNew([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'care_type' => $attributes['care_type'] ?? null,
        ]);

        $existingStatus = $note->care_status;
        $note->fill($attributes);

        if ($note->exists && $existingStatus === 'completed') {
            $note->care_status = 'completed';
        }

        $note->save();
    }

    protected function cancelTicket(string $sourceType, int $sourceId, ?string $careType = null): void
    {
        $query = Note::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId);

        if ($careType) {
            $query->where('care_type', $careType);
        }

        $query->update([
            'care_status' => 'cancelled',
        ]);
    }

    public function cancelBySource(string $sourceType, int $sourceId, ?string $careType = null): void
    {
        $this->cancelTicket($sourceType, $sourceId, $careType);
    }

    protected function resolveCustomerId(int $patientId): ?int
    {
        return Patient::query()->whereKey($patientId)->value('customer_id');
    }
}
