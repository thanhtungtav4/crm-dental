<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierScenarioSeeder extends Seeder
{
    public const ACTIVE_SUPPLIER_CODE = 'SUP-QA-ACTIVE';

    public const INACTIVE_SUPPLIER_CODE = 'SUP-QA-INACTIVE';

    public const FACTORY_ORDER_NO = 'FO-QA-SUP-001';

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
        $managerId = is_numeric($userIdsByEmail->get('manager.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('manager.q1@demo.ident.test')
            : null;
        $doctorId = is_numeric($userIdsByEmail->get('doctor.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('doctor.q1@demo.ident.test')
            : null;
        $frontDeskId = is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
            : null;

        $customer = Customer::query()
            ->where('branch_id', (int) $branchId)
            ->where('source_detail', 'seed:supplier-scenario:factory-order')
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => (int) $branchId,
            'full_name' => 'QA Supplier Factory Order',
            'phone' => '0909004091',
            'phone_search_hash' => Customer::phoneSearchHash('0909004091'),
            'email' => 'qa.supplier.factory@demo.ident.test',
            'email_search_hash' => Customer::emailSearchHash('qa.supplier.factory@demo.ident.test'),
            'source' => 'qa_seed',
            'source_detail' => 'seed:supplier-scenario:factory-order',
            'status' => 'converted',
            'assigned_to' => $frontDeskId,
            'notes' => 'Seeded supplier module QA scenario.',
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $customer->save();

        $patient = Patient::query()->firstOrNew([
            'patient_code' => 'PAT-QA-SUP-001',
        ]);
        $patient->fill([
            'customer_id' => $customer->id,
            'patient_code' => 'PAT-QA-SUP-001',
            'first_branch_id' => (int) $branchId,
            'full_name' => 'QA Supplier Factory Order',
            'phone' => '0909004091',
            'phone_search_hash' => Patient::phoneSearchHash('0909004091'),
            'email' => 'qa.supplier.factory@demo.ident.test',
            'email_search_hash' => Patient::emailSearchHash('qa.supplier.factory@demo.ident.test'),
            'gender' => 'female',
            'address' => 'Seed supplier scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $frontDeskId,
            'status' => 'active',
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $patient->save();

        $activeSupplier = Supplier::query()->updateOrCreate(
            ['code' => self::ACTIVE_SUPPLIER_CODE],
            [
                'name' => 'QA Active Labo',
                'payment_terms' => '30_days',
                'active' => true,
            ],
        );

        Supplier::query()->updateOrCreate(
            ['code' => self::INACTIVE_SUPPLIER_CODE],
            [
                'name' => 'QA Inactive Labo',
                'payment_terms' => '30_days',
                'active' => false,
            ],
        );

        $order = FactoryOrder::query()->updateOrCreate(
            ['order_no' => self::FACTORY_ORDER_NO],
            [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'doctor_id' => $doctorId,
                'supplier_id' => $activeSupplier->id,
                'requested_by' => $managerId,
                'status' => FactoryOrder::STATUS_DRAFT,
                'priority' => 'normal',
                'vendor_name' => $activeSupplier->name,
                'notes' => 'Seeded supplier workflow scenario.',
            ],
        );

        FactoryOrderItem::query()->updateOrCreate(
            [
                'factory_order_id' => $order->id,
                'item_name' => 'QA Zirconia Crown',
            ],
            [
                'quantity' => 1,
                'unit_price' => 1_800_000,
                'status' => 'ordered',
                'notes' => 'Seeded supplier workflow item.',
            ],
        );
    }
}
