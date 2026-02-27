<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Services\MasterPatientIndexService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;

class SyncMasterPatientIndex extends Command
{
    protected $signature = 'mpi:sync {--patient_id= : Đồng bộ 1 bệnh nhân cụ thể} {--identity_type= : Lọc duplicate theo identity type} {--show-duplicates : In danh sách duplicate groups} {--show-cases : In queue duplicate cases đang mở} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Đồng bộ MPI và kiểm tra duplicate liên chi nhánh.';

    public function __construct(protected MasterPatientIndexService $mpiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::MASTER_DATA_SYNC,
            'Bạn không có quyền chạy đồng bộ MPI.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $patientId = $this->option('patient_id') !== null
            ? (int) $this->option('patient_id')
            : null;

        $syncedPatients = 0;
        $syncedIdentities = 0;
        $duplicatePatients = 0;

        $query = Patient::query()->when($patientId !== null, fn ($innerQuery) => $innerQuery->whereKey($patientId));

        $query->chunkById(200, function ($patients) use (&$syncedPatients, &$syncedIdentities, &$duplicatePatients, $dryRun): void {
            foreach ($patients as $patient) {
                $syncedIdentities += $this->mpiService->syncForPatient($patient, ! $dryRun);
                $syncedPatients++;

                if ($this->mpiService->hasCrossBranchDuplicate($patient)) {
                    $duplicatePatients++;
                }
            }
        });

        $duplicateRows = collect();
        $duplicateCases = collect();

        if ((bool) $this->option('show-duplicates')) {
            $duplicateRows = $this->mpiService->duplicateGroups(
                $this->option('identity_type') ? (string) $this->option('identity_type') : null,
            );

            $this->table(
                ['Identity Type', 'Identity Value', 'Patients', 'Branches'],
                $duplicateRows
                    ->map(fn ($row) => [
                        $row->identity_type,
                        $row->identity_value,
                        $row->patient_count,
                        $row->branch_count,
                    ])
                    ->all()
            );
        }

        if ((bool) $this->option('show-cases')) {
            $duplicateCases = $this->mpiService->openDuplicateCases(
                $this->option('identity_type') ? (string) $this->option('identity_type') : null,
            );

            $this->table(
                ['Case ID', 'Identity', 'Value', 'Patients', 'Branches', 'Confidence', 'Status'],
                $duplicateCases
                    ->map(fn ($case) => [
                        $case->id,
                        $case->identity_type,
                        $case->identity_value,
                        count($case->matched_patient_ids ?? []),
                        count($case->matched_branch_ids ?? []),
                        number_format((float) $case->confidence_score, 2),
                        $case->status,
                    ])
                    ->all()
            );
        }

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_MASTER_PATIENT_INDEX,
                entityId: $patientId ?? 0,
                action: AuditLog::ACTION_SYNC,
                actorId: auth()->id(),
                metadata: [
                    'patient_id' => $patientId,
                    'synced_patients' => $syncedPatients,
                    'synced_identities' => $syncedIdentities,
                    'duplicate_patients' => $duplicatePatients,
                    'duplicate_groups' => $duplicateRows->count(),
                    'duplicate_cases_open' => $duplicateCases->count(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] MPI sync done. patients={$syncedPatients}, identities={$syncedIdentities}, duplicate_patients={$duplicatePatients}");

        return self::SUCCESS;
    }
}
