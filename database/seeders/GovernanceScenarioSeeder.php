<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class GovernanceScenarioSeeder extends Seeder
{
    public const ASSIGNED_DOCTOR_EMAIL = 'qa.gov.assigned@demo.ident.test';

    public const HIDDEN_USER_EMAIL = 'qa.gov.hidden@demo.ident.test';

    public function run(): void
    {
        $branchIdsByCode = Branch::query()
            ->whereIn('code', ['HCM-Q1', 'HN-CG'])
            ->pluck('id', 'code');

        $q1BranchId = $branchIdsByCode->get('HCM-Q1');
        $hiddenBranchId = $branchIdsByCode->get('HN-CG');

        if (! is_numeric($q1BranchId) || ! is_numeric($hiddenBranchId)) {
            return;
        }

        $assignedDoctor = User::query()->firstOrNew([
            'email' => self::ASSIGNED_DOCTOR_EMAIL,
        ]);
        $assignedDoctor->fill([
            'name' => 'QA Governance Assigned Doctor',
            'email' => self::ASSIGNED_DOCTOR_EMAIL,
            'password' => LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD,
            'branch_id' => (int) $hiddenBranchId,
            'phone' => '0909007091',
            'specialty' => 'Tong quat',
        ]);
        $assignedDoctor->save();
        $assignedDoctor->syncRoles(['Doctor']);

        $hiddenUser = User::query()->firstOrNew([
            'email' => self::HIDDEN_USER_EMAIL,
        ]);
        $hiddenUser->fill([
            'name' => 'QA Governance Hidden User',
            'email' => self::HIDDEN_USER_EMAIL,
            'password' => LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD,
            'branch_id' => (int) $hiddenBranchId,
            'phone' => '0909007092',
            'specialty' => null,
        ]);
        $hiddenUser->save();
        $hiddenUser->syncRoles(['CSKH']);

        DoctorBranchAssignment::query()->updateOrCreate(
            [
                'user_id' => $assignedDoctor->id,
                'branch_id' => (int) $q1BranchId,
            ],
            [
                'is_active' => true,
                'is_primary' => false,
                'assigned_from' => today()->subDay()->toDateString(),
                'assigned_until' => null,
            ],
        );
    }
}
