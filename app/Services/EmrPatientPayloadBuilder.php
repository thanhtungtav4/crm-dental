<?php

namespace App\Services;

use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\VisitEpisode;

class EmrPatientPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Patient $patient): array
    {
        $patient->loadMissing([
            'medicalRecord',
            'visitEpisodes' => fn ($query) => $query->latest('scheduled_at')->limit(20),
            'treatmentPlans' => fn ($query) => $query->latest('updated_at')->limit(20),
            'treatmentPlans.planItems',
            'clinicalOrders' => fn ($query) => $query->latest('updated_at')->limit(20),
            'clinicalOrders.results',
            'clinicalResults' => fn ($query) => $query->latest('updated_at')->limit(20),
            'prescriptions' => fn ($query) => $query->latest('updated_at')->limit(20),
            'prescriptions.items',
        ]);

        return [
            'patient' => [
                'id' => (int) $patient->id,
                'patient_code' => (string) $patient->patient_code,
                'full_name' => (string) $patient->full_name,
                'gender' => $patient->gender,
                'birthday' => $patient->birthday?->toDateString(),
                'phone' => $patient->phone,
                'phone_secondary' => $patient->phone_secondary,
                'email' => $patient->email,
                'address' => $patient->address,
                'first_branch_id' => $patient->first_branch_id ? (int) $patient->first_branch_id : null,
                'primary_doctor_id' => $patient->primary_doctor_id ? (int) $patient->primary_doctor_id : null,
                'owner_staff_id' => $patient->owner_staff_id ? (int) $patient->owner_staff_id : null,
                'medical_history' => $patient->medical_history,
                'updated_at' => $patient->updated_at?->toISOString(),
            ],
            'medical_record' => $this->mapMedicalRecord($patient),
            'encounter' => [
                'records' => $patient->visitEpisodes
                    ->map(fn (VisitEpisode $episode): array => $this->mapEncounter($episode))
                    ->values()
                    ->all(),
            ],
            'treatment' => [
                'plans' => $patient->treatmentPlans
                    ->map(fn (TreatmentPlan $plan): array => $this->mapTreatmentPlan($plan))
                    ->values()
                    ->all(),
            ],
            'order' => [
                'records' => $patient->clinicalOrders
                    ->map(fn (ClinicalOrder $order): array => $this->mapClinicalOrder($order))
                    ->values()
                    ->all(),
            ],
            'result' => [
                'records' => $patient->clinicalResults
                    ->map(fn (ClinicalResult $result): array => $this->mapClinicalResult($result))
                    ->values()
                    ->all(),
            ],
            'prescription' => [
                'records' => $patient->prescriptions
                    ->map(fn (Prescription $prescription): array => $this->mapPrescription($prescription))
                    ->values()
                    ->all(),
            ],
            'meta' => [
                'generated_at' => now()->toISOString(),
                'schema_version' => 'emr.v1',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checksum(array $payload): string
    {
        $normalizedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $normalizedPayload ?: '');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapMedicalRecord(Patient $patient): ?array
    {
        $record = $patient->medicalRecord;

        if (! $record) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'allergies' => array_values((array) ($record->allergies ?? [])),
            'chronic_diseases' => array_values((array) ($record->chronic_diseases ?? [])),
            'current_medications' => array_values((array) ($record->current_medications ?? [])),
            'blood_type' => $record->blood_type,
            'insurance_provider' => $record->insurance_provider,
            'insurance_number' => $record->insurance_number,
            'insurance_expiry_date' => $record->insurance_expiry_date?->toDateString(),
            'emergency_contact_name' => $record->emergency_contact_name,
            'emergency_contact_phone' => $record->emergency_contact_phone,
            'emergency_contact_email' => $record->emergency_contact_email,
            'emergency_contact_relationship' => $record->emergency_contact_relationship,
            'additional_notes' => $record->additional_notes,
            'updated_by' => $record->updated_by ? (int) $record->updated_by : null,
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapTreatmentPlan(TreatmentPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'branch_id' => $plan->branch_id ? (int) $plan->branch_id : null,
            'doctor_id' => $plan->doctor_id ? (int) $plan->doctor_id : null,
            'title' => $plan->title,
            'status' => $plan->status,
            'priority' => $plan->priority,
            'expected_start_date' => $plan->expected_start_date?->toDateString(),
            'expected_end_date' => $plan->expected_end_date?->toDateString(),
            'actual_start_date' => $plan->actual_start_date?->toDateString(),
            'actual_end_date' => $plan->actual_end_date?->toDateString(),
            'total_estimated_cost' => (float) ($plan->total_estimated_cost ?? 0),
            'total_cost' => (float) ($plan->total_cost ?? 0),
            'progress_percentage' => (int) ($plan->progress_percentage ?? 0),
            'items' => $plan->planItems
                ->map(fn (PlanItem $item): array => [
                    'id' => (int) $item->id,
                    'service_id' => $item->service_id ? (int) $item->service_id : null,
                    'name' => $item->name,
                    'status' => $item->status,
                    'approval_status' => $item->approval_status,
                    'tooth_ids' => array_values((array) ($item->tooth_ids ?? [])),
                    'diagnosis_ids' => array_values((array) ($item->diagnosis_ids ?? [])),
                    'quantity' => (float) ($item->quantity ?? 1),
                    'final_amount' => (float) ($item->final_amount ?? 0),
                    'required_visits' => (int) ($item->required_visits ?? 0),
                    'completed_visits' => (int) ($item->completed_visits ?? 0),
                    'started_at' => $item->started_at?->toDateString(),
                    'completed_at' => $item->completed_at?->toDateString(),
                    'updated_at' => $item->updated_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'updated_at' => $plan->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPrescription(Prescription $prescription): array
    {
        return [
            'id' => (int) $prescription->id,
            'visit_episode_id' => $prescription->visit_episode_id ? (int) $prescription->visit_episode_id : null,
            'prescription_code' => (string) $prescription->prescription_code,
            'prescription_name' => $prescription->prescription_name,
            'doctor_id' => $prescription->doctor_id ? (int) $prescription->doctor_id : null,
            'treatment_session_id' => $prescription->treatment_session_id ? (int) $prescription->treatment_session_id : null,
            'treatment_date' => $prescription->treatment_date?->toDateString(),
            'notes' => $prescription->notes,
            'items' => $prescription->items
                ->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'medication_name' => $item->medication_name,
                    'dosage' => $item->dosage,
                    'duration' => $item->duration,
                    'quantity' => (float) ($item->quantity ?? 0),
                    'unit' => $item->unit,
                    'instructions' => $item->instructions,
                    'notes' => $item->notes,
                ])
                ->values()
                ->all(),
            'updated_at' => $prescription->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapClinicalOrder(ClinicalOrder $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_code' => (string) $order->order_code,
            'order_type' => (string) $order->order_type,
            'status' => (string) $order->status,
            'patient_id' => $order->patient_id ? (int) $order->patient_id : null,
            'visit_episode_id' => $order->visit_episode_id ? (int) $order->visit_episode_id : null,
            'clinical_note_id' => $order->clinical_note_id ? (int) $order->clinical_note_id : null,
            'branch_id' => $order->branch_id ? (int) $order->branch_id : null,
            'ordered_by' => $order->ordered_by ? (int) $order->ordered_by : null,
            'requested_at' => $order->requested_at?->toISOString(),
            'completed_at' => $order->completed_at?->toISOString(),
            'payload' => $order->payload,
            'notes' => $order->notes,
            'result_ids' => $order->results->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapClinicalResult(ClinicalResult $result): array
    {
        return [
            'id' => (int) $result->id,
            'clinical_order_id' => $result->clinical_order_id ? (int) $result->clinical_order_id : null,
            'result_code' => (string) $result->result_code,
            'status' => (string) $result->status,
            'patient_id' => $result->patient_id ? (int) $result->patient_id : null,
            'visit_episode_id' => $result->visit_episode_id ? (int) $result->visit_episode_id : null,
            'branch_id' => $result->branch_id ? (int) $result->branch_id : null,
            'verified_by' => $result->verified_by ? (int) $result->verified_by : null,
            'resulted_at' => $result->resulted_at?->toISOString(),
            'verified_at' => $result->verified_at?->toISOString(),
            'payload' => $result->payload,
            'interpretation' => $result->interpretation,
            'notes' => $result->notes,
            'updated_at' => $result->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapEncounter(VisitEpisode $episode): array
    {
        return [
            'id' => (int) $episode->id,
            'appointment_id' => $episode->appointment_id ? (int) $episode->appointment_id : null,
            'branch_id' => $episode->branch_id ? (int) $episode->branch_id : null,
            'doctor_id' => $episode->doctor_id ? (int) $episode->doctor_id : null,
            'status' => $episode->status,
            'scheduled_at' => $episode->scheduled_at?->toISOString(),
            'check_in_at' => $episode->check_in_at?->toISOString(),
            'arrived_at' => $episode->arrived_at?->toISOString(),
            'in_chair_at' => $episode->in_chair_at?->toISOString(),
            'check_out_at' => $episode->check_out_at?->toISOString(),
            'planned_duration_minutes' => $episode->planned_duration_minutes !== null
                ? (int) $episode->planned_duration_minutes
                : null,
            'actual_duration_minutes' => $episode->actual_duration_minutes !== null
                ? (int) $episode->actual_duration_minutes
                : null,
            'chair_minutes' => $episode->chair_minutes !== null
                ? (int) $episode->chair_minutes
                : null,
            'waiting_minutes' => $episode->waiting_minutes !== null
                ? (int) $episode->waiting_minutes
                : null,
            'updated_at' => $episode->updated_at?->toISOString(),
        ];
    }
}
