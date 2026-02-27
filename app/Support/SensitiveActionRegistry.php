<?php

namespace App\Support;

class SensitiveActionRegistry
{
    /**
     * @return array<string, array{
     *     allowed_roles:array<int, string>,
     *     guard_markers:array<int, array{path:string, contains:string}>,
     *     authorization_test_markers:array<int, array{path:string, contains:string}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            ActionPermission::PAYMENT_REVERSAL => [
                'allowed_roles' => ['Admin', 'Manager'],
                'guard_markers' => [
                    ['path' => 'app/Models/Payment.php', 'contains' => 'ActionPermission::PAYMENT_REVERSAL'],
                    ['path' => 'app/Models/Invoice.php', 'contains' => 'ActionPermission::PAYMENT_REVERSAL'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::PAYMENT_REVERSAL'],
                ],
            ],
            ActionPermission::APPOINTMENT_OVERRIDE => [
                'allowed_roles' => ['Admin', 'Manager', 'Doctor'],
                'guard_markers' => [
                    ['path' => 'app/Models/Appointment.php', 'contains' => 'ActionPermission::APPOINTMENT_OVERRIDE'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::APPOINTMENT_OVERRIDE'],
                ],
            ],
            ActionPermission::PLAN_APPROVAL => [
                'allowed_roles' => ['Admin', 'Manager', 'Doctor'],
                'guard_markers' => [
                    ['path' => 'app/Models/PlanItem.php', 'contains' => 'ActionPermission::PLAN_APPROVAL'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::PLAN_APPROVAL'],
                ],
            ],
            ActionPermission::AUTOMATION_RUN => [
                'allowed_roles' => ['Admin', 'Manager', 'CSKH', 'AutomationService'],
                'guard_markers' => [
                    ['path' => 'app/Console/Commands/SnapshotOperationalKpis.php', 'contains' => 'ActionPermission::AUTOMATION_RUN'],
                    ['path' => 'app/Console/Commands/CheckSnapshotSla.php', 'contains' => 'ActionPermission::AUTOMATION_RUN'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::AUTOMATION_RUN'],
                ],
            ],
            ActionPermission::MASTER_DATA_SYNC => [
                'allowed_roles' => ['Admin', 'Manager'],
                'guard_markers' => [
                    ['path' => 'app/Services/MasterDataSyncService.php', 'contains' => 'ActionPermission::MASTER_DATA_SYNC'],
                    ['path' => 'app/Console/Commands/SyncMasterData.php', 'contains' => 'ActionPermission::MASTER_DATA_SYNC'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::MASTER_DATA_SYNC'],
                ],
            ],
            ActionPermission::INSURANCE_CLAIM_DECISION => [
                'allowed_roles' => ['Admin', 'Manager'],
                'guard_markers' => [
                    ['path' => 'app/Models/InsuranceClaim.php', 'contains' => 'ActionPermission::INSURANCE_CLAIM_DECISION'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::INSURANCE_CLAIM_DECISION'],
                ],
            ],
            ActionPermission::MPI_DEDUPE_REVIEW => [
                'allowed_roles' => ['Admin', 'Manager'],
                'guard_markers' => [
                    ['path' => 'app/Services/MasterPatientMergeService.php', 'contains' => 'ActionPermission::MPI_DEDUPE_REVIEW'],
                    ['path' => 'app/Models/MasterPatientDuplicate.php', 'contains' => 'ActionPermission::MPI_DEDUPE_REVIEW'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::MPI_DEDUPE_REVIEW'],
                ],
            ],
            ActionPermission::PATIENT_BRANCH_TRANSFER => [
                'allowed_roles' => ['Admin', 'Manager', 'CSKH'],
                'guard_markers' => [
                    ['path' => 'app/Services/PatientBranchTransferService.php', 'contains' => 'ActionPermission::PATIENT_BRANCH_TRANSFER'],
                ],
                'authorization_test_markers' => [
                    ['path' => 'tests/Feature/ActionSecurityCoverageTest.php', 'contains' => 'ActionPermission::PATIENT_BRANCH_TRANSFER'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function roleMatrix(): array
    {
        return collect(self::definitions())
            ->map(fn (array $definition): array => $definition['allowed_roles'])
            ->all();
    }
}
