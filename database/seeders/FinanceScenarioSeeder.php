<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class FinanceScenarioSeeder extends Seeder
{
    public const OVERDUE_INVOICE_NO = 'INV-QA-FIN-001';

    public const REVERSAL_INVOICE_NO = 'INV-QA-FIN-002';

    public const INSTALLMENT_INVOICE_NO = 'INV-QA-FIN-003';

    public const REVERSAL_RECEIPT_TRANSACTION_REF = 'QA-FIN-REV-RECEIPT';

    public const INSTALLMENT_PLAN_CODE = 'INS-QA-FIN-001';

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

        $overduePatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Finance Overdue',
            phone: '0909003091',
            email: 'qa.finance.overdue@demo.ident.test',
            customerSourceDetail: 'seed:finance-scenario:overdue',
            patientCode: 'PAT-QA-FIN-001',
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );
        $reversalPatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Finance Reversal',
            phone: '0909003092',
            email: 'qa.finance.reversal@demo.ident.test',
            customerSourceDetail: 'seed:finance-scenario:reversal',
            patientCode: 'PAT-QA-FIN-002',
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );
        $installmentPatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Finance Installment',
            phone: '0909003093',
            email: 'qa.finance.installment@demo.ident.test',
            customerSourceDetail: 'seed:finance-scenario:installment',
            patientCode: 'PAT-QA-FIN-003',
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );

        $overdueInvoice = Invoice::query()->updateOrCreate(
            ['invoice_no' => self::OVERDUE_INVOICE_NO],
            [
                'patient_id' => $overduePatient->id,
                'branch_id' => $overduePatient->first_branch_id,
                'subtotal' => 4_500_000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 4_500_000,
                'paid_amount' => 0,
                'status' => Invoice::STATUS_ISSUED,
                'issued_at' => CarbonImmutable::now()->subDays(14),
                'due_date' => CarbonImmutable::now()->subDays(10)->toDateString(),
            ],
        );

        $reversalInvoice = Invoice::query()->updateOrCreate(
            ['invoice_no' => self::REVERSAL_INVOICE_NO],
            [
                'patient_id' => $reversalPatient->id,
                'branch_id' => $reversalPatient->first_branch_id,
                'subtotal' => 2_000_000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 2_000_000,
                'paid_amount' => 0,
                'status' => Invoice::STATUS_ISSUED,
                'issued_at' => CarbonImmutable::now()->subDays(5),
                'due_date' => CarbonImmutable::now()->addDays(7)->toDateString(),
            ],
        );

        $installmentInvoice = Invoice::query()->updateOrCreate(
            ['invoice_no' => self::INSTALLMENT_INVOICE_NO],
            [
                'patient_id' => $installmentPatient->id,
                'branch_id' => $installmentPatient->first_branch_id,
                'subtotal' => 900_000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 900_000,
                'paid_amount' => 0,
                'status' => Invoice::STATUS_ISSUED,
                'issued_at' => CarbonImmutable::now()->subMonths(3),
                'due_date' => CarbonImmutable::now()->subMonths(2)->toDateString(),
            ],
        );

        Payment::query()->updateOrCreate(
            ['transaction_ref' => self::REVERSAL_RECEIPT_TRANSACTION_REF],
            [
                'invoice_id' => $reversalInvoice->id,
                'branch_id' => $reversalInvoice->branch_id,
                'amount' => 600_000,
                'direction' => 'receipt',
                'is_deposit' => false,
                'method' => 'cash',
                'payment_source' => 'patient',
                'paid_at' => CarbonImmutable::now()->subDay(),
                'received_by' => $managerId,
                'note' => 'Seeded receipt for reversal smoke.',
                'refund_reason' => null,
                'reversal_of_id' => null,
                'reversed_at' => null,
                'reversed_by' => null,
            ],
        );

        $reversalInvoice->refresh();
        $reversalInvoice->updatePaidAmount();
        $overdueInvoice->refresh();
        $installmentInvoice->refresh();

        InstallmentPlan::query()->updateOrCreate(
            ['plan_code' => self::INSTALLMENT_PLAN_CODE],
            [
                'invoice_id' => $installmentInvoice->id,
                'patient_id' => $installmentPatient->id,
                'branch_id' => $installmentInvoice->branch_id,
                'financed_amount' => 900_000,
                'down_payment_amount' => 0,
                'remaining_amount' => 900_000,
                'number_of_installments' => 3,
                'installment_amount' => 300_000,
                'start_date' => CarbonImmutable::now()->subMonths(3)->toDateString(),
                'next_due_date' => CarbonImmutable::now()->subMonths(3)->toDateString(),
                'end_date' => CarbonImmutable::now()->subMonth()->toDateString(),
                'status' => InstallmentPlan::STATUS_ACTIVE,
                'schedule' => InstallmentPlan::buildSchedule(CarbonImmutable::now()->subMonths(3), 3, 300_000),
                'dunning_level' => 0,
                'last_dunned_at' => null,
                'notes' => 'Seeded installment dunning QA scenario.',
            ],
        );
    }

    protected function upsertScenarioPatient(
        int $branchId,
        string $fullName,
        string $phone,
        ?string $email,
        string $customerSourceDetail,
        string $patientCode,
        ?int $ownerStaffId,
        ?int $doctorId,
        int $actorId,
    ): Patient {
        $customer = Customer::query()
            ->where('branch_id', $branchId)
            ->where('source_detail', $customerSourceDetail)
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => $branchId,
            'full_name' => $fullName,
            'phone' => $phone,
            'phone_search_hash' => Customer::phoneSearchHash($phone),
            'email' => $email,
            'email_search_hash' => Customer::emailSearchHash($email),
            'source' => 'qa_seed',
            'source_detail' => $customerSourceDetail,
            'status' => 'converted',
            'assigned_to' => $ownerStaffId,
            'notes' => 'Seeded finance module QA scenario.',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $customer->save();

        $patient = Patient::query()->firstOrNew([
            'patient_code' => $patientCode,
        ]);

        $patient->fill([
            'customer_id' => $customer->id,
            'patient_code' => $patientCode,
            'first_branch_id' => $branchId,
            'full_name' => $fullName,
            'phone' => $phone,
            'phone_search_hash' => Patient::phoneSearchHash($phone),
            'email' => $email,
            'email_search_hash' => Patient::emailSearchHash($email),
            'gender' => 'male',
            'address' => 'Seed finance scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $ownerStaffId,
            'first_visit_reason' => 'Finance workflow validation',
            'status' => 'active',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $patient->save();

        return $patient->fresh();
    }
}
