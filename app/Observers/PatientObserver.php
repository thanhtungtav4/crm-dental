<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\MasterPatientIndexService;

class PatientObserver
{
    public function __construct(protected MasterPatientIndexService $mpiService) {}

    public function created(Patient $patient): void
    {
        $this->syncIdentity($patient);
    }

    public function updated(Patient $patient): void
    {
        if (! $patient->wasChanged(['phone', 'email', 'cccd', 'first_branch_id'])) {
            return;
        }

        $this->syncIdentity($patient);
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
            ],
        );
    }
}
