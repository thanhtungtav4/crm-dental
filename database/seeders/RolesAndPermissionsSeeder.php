<?php

namespace Database\Seeders;

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

        $pages = [
            'SystemSettings',
            'IntegrationSettings',
        ];

        $extraPermissions = [
            'View:IntegrationSettingsAuditLog',
        ];

        // Ensure permissions exist (compatible with Shield)
        foreach ($resources as $res) {
            foreach ($adminOnlyPrefixes as $prefix) {
                Permission::firstOrCreate(['name' => $prefix . ':' . $res]);
            }
        }

        foreach ($pages as $page) {
            Permission::firstOrCreate(['name' => 'View:' . $page]);
        }

        foreach ($extraPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::findByName('Admin');
        $manager = Role::findByName('Manager');
        $doctor = Role::findByName('Doctor');
        $cskh = Role::findByName('CSKH');

        // Admin: all permissions
        $admin->syncPermissions(Permission::all());

        // Manager: CRUD (ViewAny, View, Create, Update, Delete)
        $managerPerms = [];
        foreach ($resources as $res) {
            foreach ($basicPrefixes as $prefix) {
                $managerPerms[] = $prefix . ':' . $res; // e.g. ViewAny:Customer
            }
        }
        foreach ($pages as $page) {
            $managerPerms[] = 'View:' . $page;
        }
        $managerPerms[] = 'View:IntegrationSettingsAuditLog';
        $manager->syncPermissions($managerPerms);

        // Doctor: View/Update Patients & Plans. Manage Sessions.
        $doctorPerms = [];
        // Patients & Plans
        foreach (['Patient', 'TreatmentPlan'] as $res) {
            foreach (['ViewAny', 'View', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix . ':' . $res;
            }
        }
        // Sessions (Full control except maybe delete?)
        foreach (['TreatmentSession'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix . ':' . $res;
            }
        }
        // Appointments
        foreach (['Appointment'] as $res) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $prefix) {
                $doctorPerms[] = $prefix . ':' . $res;
            }
        }
        $doctor->syncPermissions($doctorPerms);

        // CSKH: Customers & Notes
        $cskhPerms = [];
        foreach (['Customer'] as $res) {
            foreach (['ViewAny', 'View'] as $prefix) {
                $cskhPerms[] = $prefix . ':' . $res;
            }
        }
        foreach (['Note'] as $res) {
            foreach (['ViewAny', 'View', 'Create'] as $prefix) {
                $cskhPerms[] = $prefix . ':' . $res;
            }
        }
        $cskh->syncPermissions($cskhPerms);
    }
}
