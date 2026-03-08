<?php

namespace Database\Seeders;

use App\Services\GovernanceResourcePermissionBaselineService;
use App\Support\ActionPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define roles
        $roles = [
            'Admin',
            'Manager',
            'Doctor',
            'CSKH',
            'AutomationService',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Common Filament Shield permission prefixes (PascalCase)
        $basicPrefixes = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];
        $adminOnlyPrefixes = array_merge($basicPrefixes, ['DeleteAny', 'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder']);

        // Resource names (PascalCase) matching Shield generation
        $resources = [
            'Branch',
            'User',
            'Customer',
            'CustomerGroup',
            'PromotionGroup',
            'Patient',
            'TreatmentPlan',
            'TreatmentSession',
            'PlanItem',
            'Material',
            'MaterialBatch',
            'TreatmentMaterial',
            'Invoice',
            'Payment',
            'PatientWallet',
            'Prescription',
            'PatientPhoto',
            'ClinicalMediaAsset',
            'PatientMedicalRecord',
            'ReceiptExpense',
            'Note',
            'Appointment',
            'Service',
            'ServiceCategory',
            'Supplier',
            'DiseaseGroup',
            'Disease',
            'ToothCondition',
            'Role',
        ];

        $managerManagedResources = array_values(array_diff(
            $resources,
            GovernanceResourcePermissionBaselineService::governanceResources(),
        ));

        $pages = [
            'SystemSettings',
            'IntegrationSettings',
            'OpsControlCenter',
            'FrontdeskControlCenter',
            'DeliveryOpsCenter',
            'FinancialDashboard',
            'DentalChainReport',
            'DentalApp',
        ];

        $extraPermissions = [
            'View:IntegrationSettingsAuditLog',
            'Manage:IntegrationRuntimeSettings',
            'Manage:IntegrationSecrets',
        ];

        // Ensure permissions exist (compatible with Shield)
        foreach ($resources as $res) {
            foreach ($adminOnlyPrefixes as $prefix) {
                Permission::firstOrCreate(['name' => $prefix.':'.$res]);
            }
        }

        foreach ($pages as $page) {
            Permission::firstOrCreate(['name' => 'View:'.$page]);
        }

        foreach (array_merge($extraPermissions, ActionPermission::all()) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::findByName('Admin');
        $manager = Role::findByName('Manager');
        $doctor = Role::findByName('Doctor');
        $cskh = Role::findByName('CSKH');
        $automationService = Role::findByName('AutomationService');

        // Admin: all permissions
        $admin->syncPermissions(Permission::all());

        // Manager: CRUD on operational resources only.
        $managerPerms = [];
        foreach ($managerManagedResources as $res) {
            foreach ($basicPrefixes as $prefix) {
                $managerPerms[] = $prefix.':'.$res; // e.g. ViewAny:Customer
            }
        }
        foreach ($pages as $page) {
            $managerPerms[] = 'View:'.$page;
        }
        $managerPerms[] = 'View:IntegrationSettingsAuditLog';
        $managerPerms = array_merge($managerPerms, [
            ActionPermission::PAYMENT_REVERSAL,
            ActionPermission::WALLET_ADJUST,
            ActionPermission::APPOINTMENT_OVERRIDE,
            ActionPermission::PLAN_APPROVAL,
            ActionPermission::AUTOMATION_RUN,
            ActionPermission::MASTER_DATA_SYNC,
            ActionPermission::INSURANCE_CLAIM_DECISION,
            ActionPermission::MPI_DEDUPE_REVIEW,
            ActionPermission::PATIENT_BRANCH_TRANSFER,
            ActionPermission::EMR_CLINICAL_WRITE,
            ActionPermission::EMR_EVIDENCE_OVERRIDE,
            ActionPermission::EMR_RECORD_EXPORT,
            ActionPermission::EMR_SYNC_PUSH,
        ]);
        $manager->syncPermissions($managerPerms);

        // Doctor: View/Update Patients & Plans. Manage Sessions.
        $doctorPerms = [];
        // Patients & Plans
        foreach (['Patient', 'TreatmentPlan'] as $res) {
            foreach (['ViewAny', 'View', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix.':'.$res;
            }
        }
        // Sessions (Full control except maybe delete?)
        foreach (['TreatmentSession'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix.':'.$res;
            }
        }
        // Appointments
        foreach (['Appointment'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix.':'.$res;
            }
        }
        $doctorPerms = array_merge($doctorPerms, [
            'View:DeliveryOpsCenter',
            ActionPermission::APPOINTMENT_OVERRIDE,
            ActionPermission::PLAN_APPROVAL,
            ActionPermission::EMR_CLINICAL_WRITE,
            ActionPermission::EMR_RECORD_EXPORT,
        ]);
        $doctor->syncPermissions($doctorPerms);

        // CSKH: Front-office / tư vấn flow
        $cskhPerms = [];
        foreach (['Customer'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $cskhPerms[] = $prefix.':'.$res;
            }
        }
        foreach (['Patient'] as $res) {
            foreach (['ViewAny', 'View', 'Create'] as $prefix) {
                $cskhPerms[] = $prefix.':'.$res;
            }
        }
        foreach (['Appointment'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $cskhPerms[] = $prefix.':'.$res;
            }
        }
        foreach (['Note'] as $res) {
            foreach (['ViewAny', 'View', 'Create'] as $prefix) {
                $cskhPerms[] = $prefix.':'.$res;
            }
        }
        $cskhPerms[] = 'View:FrontdeskControlCenter';
        $cskhPerms[] = ActionPermission::AUTOMATION_RUN;
        $cskhPerms[] = ActionPermission::PATIENT_BRANCH_TRANSFER;
        $cskh->syncPermissions($cskhPerms);

        $automationService->syncPermissions([
            ActionPermission::AUTOMATION_RUN,
            ActionPermission::EMR_SYNC_PUSH,
        ]);
    }
}
