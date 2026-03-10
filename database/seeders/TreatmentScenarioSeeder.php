<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class TreatmentScenarioSeeder extends Seeder
{
    public const PLAN_TITLE = 'QA Treatment Workflow Plan';

    public const MATERIAL_SKU = 'TRT-QA-MAT-001';

    public const MATERIAL_BATCH_NUMBER = 'LOT-QA-TRT-001';

    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HCM-Q1')->value('id');
        $userIdsByEmail = User::query()
            ->whereIn('email', [
                'admin@demo.ident.test',
                'manager.q1@demo.ident.test',
                'doctor.q1@demo.ident.test',
                'cskh.q1@demo.ident.test',
            ])
            ->pluck('id', 'email');

        if (! is_numeric($branchId) || ! is_numeric($userIdsByEmail->get('admin@demo.ident.test'))) {
            return;
        }

        $adminId = (int) $userIdsByEmail->get('admin@demo.ident.test');
        $doctorId = is_numeric($userIdsByEmail->get('doctor.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('doctor.q1@demo.ident.test')
            : null;
        $frontDeskId = is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
            : null;

        $customer = Customer::query()
            ->where('branch_id', (int) $branchId)
            ->where('source_detail', 'seed:treatment-scenario:workflow')
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => (int) $branchId,
            'full_name' => 'QA Treatment Workflow',
            'phone' => '0909005091',
            'phone_search_hash' => Customer::phoneSearchHash('0909005091'),
            'email' => 'qa.treatment.workflow@demo.ident.test',
            'email_search_hash' => Customer::emailSearchHash('qa.treatment.workflow@demo.ident.test'),
            'source' => 'qa_seed',
            'source_detail' => 'seed:treatment-scenario:workflow',
            'status' => 'converted',
            'assigned_to' => $frontDeskId,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $customer->save();

        $patient = Patient::query()->firstOrNew([
            'patient_code' => 'PAT-QA-TRT-001',
        ]);
        $patient->fill([
            'customer_id' => $customer->id,
            'patient_code' => 'PAT-QA-TRT-001',
            'first_branch_id' => (int) $branchId,
            'full_name' => 'QA Treatment Workflow',
            'phone' => '0909005091',
            'phone_search_hash' => Patient::phoneSearchHash('0909005091'),
            'email' => 'qa.treatment.workflow@demo.ident.test',
            'email_search_hash' => Patient::emailSearchHash('qa.treatment.workflow@demo.ident.test'),
            'gender' => 'male',
            'address' => 'Seed treatment scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $frontDeskId,
            'status' => 'active',
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $patient->save();

        $plan = TreatmentPlan::query()->updateOrCreate(
            ['title' => self::PLAN_TITLE],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctorId,
                'branch_id' => $patient->first_branch_id,
                'notes' => 'Seeded treatment workflow scenario.',
                'status' => TreatmentPlan::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'actual_start_date' => null,
                'actual_end_date' => null,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        );

        $planItem = PlanItem::query()->updateOrCreate(
            [
                'treatment_plan_id' => $plan->id,
                'name' => 'QA Treatment Step',
            ],
            [
                'quantity' => 1,
                'price' => 1_500_000,
                'estimated_cost' => 1_500_000,
                'actual_cost' => 0,
                'required_visits' => 1,
                'completed_visits' => 0,
                'status' => PlanItem::STATUS_PENDING,
                'approval_status' => PlanItem::APPROVAL_APPROVED,
                'patient_approved' => true,
            ],
        );

        TreatmentSession::query()->updateOrCreate(
            [
                'treatment_plan_id' => $plan->id,
                'plan_item_id' => $planItem->id,
            ],
            [
                'doctor_id' => $doctorId,
                'status' => 'scheduled',
                'notes' => 'Seeded treatment session for workflow smoke.',
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        );

        Material::query()->updateOrCreate(
            ['sku' => self::MATERIAL_SKU],
            [
                'branch_id' => $patient->first_branch_id,
                'name' => 'QA Treatment Resin',
                'unit' => 'kit',
                'stock_qty' => 10,
                'sale_price' => 350_000,
                'cost_price' => 200_000,
                'min_stock' => 2,
                'category' => 'dental_material',
                'manufacturer' => 'QA Treatment',
                'reorder_point' => 2,
                'storage_location' => 'QA-TREATMENT-A1',
            ],
        );

        $material = Material::query()->where('sku', self::MATERIAL_SKU)->firstOrFail();

        MaterialBatch::query()->updateOrCreate(
            ['batch_number' => self::MATERIAL_BATCH_NUMBER],
            [
                'material_id' => $material->id,
                'expiry_date' => now()->addMonths(4)->toDateString(),
                'quantity' => 10,
                'purchase_price' => 200_000,
                'received_date' => now()->subDays(5)->toDateString(),
                'status' => 'active',
            ],
        );
    }
}
