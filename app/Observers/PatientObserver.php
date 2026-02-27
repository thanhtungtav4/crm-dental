<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\MasterPatientDuplicate;
use App\Models\Patient;
use App\Services\EmrSyncEventPublisher;
use App\Services\MasterPatientIndexService;

class PatientObserver
{
    public function __construct(
        protected MasterPatientIndexService $mpiService,
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    public function created(Patient $patient): void
    {
        $this->syncIdentity($patient);
        $this->emrSyncEventPublisher->publishForPatient(
            patient: $patient,
            eventType: 'patient.created',
        );
    }

    public function updated(Patient $patient): void
    {
        if ($patient->wasChanged(['phone', 'email', 'cccd', 'first_branch_id'])) {
            $this->syncIdentity($patient);
        }

        if ($patient->wasChanged([
            'full_name',
            'phone',
            'phone_secondary',
            'email',
            'address',
            'birthday',
            'gender',
            'medical_history',
            'first_branch_id',
            'primary_doctor_id',
            'owner_staff_id',
        ])) {
            $this->emrSyncEventPublisher->publishForPatient(
                patient: $patient->fresh(),
                eventType: 'patient.updated',
            );
        }
    }

    public function deleted(Patient $patient): void
    {
        $this->mpiService->removeForPatient($patient->id);
    }

    protected function syncIdentity(Patient $patient): void
    {
        $identityCount = $this->mpiService->syncForPatient($patient);

        if (! $this->mpiService->hasCrossBranchDuplicate($patient)) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_MASTER_PATIENT_INDEX,
            entityId: $patient->id,
            action: AuditLog::ACTION_DEDUPE,
            actorId: auth()->id(),
            metadata: [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'synced_identities' => $identityCount,
                'open_duplicate_cases' => MasterPatientDuplicate::query()
                    ->where('status', MasterPatientDuplicate::STATUS_OPEN)
                    ->whereJsonContains('matched_patient_ids', $patient->id)
                    ->count(),
            ],
        );
    }
}
