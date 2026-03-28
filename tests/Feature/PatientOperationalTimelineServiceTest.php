<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FactoryOrder;
use App\Models\InsuranceClaim;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\ReceiptExpense;
use App\Models\User;
use App\Services\PatientOperationalTimelineService;
use Carbon\Carbon;

it('builds operational timeline entries for a patient from finance, appointment, care, and labo audit logs', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_INVOICE,
        'entity_id' => 101,
        'action' => AuditLog::ACTION_UPDATE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'invoice_no' => 'INV-001',
            'amount' => 1250000,
        ],
        'occurred_at' => Carbon::parse('2026-03-10 09:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_APPOINTMENT,
        'entity_id' => 202,
        'action' => AuditLog::ACTION_RESCHEDULE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'status_to' => Appointment::STATUS_RESCHEDULED,
            'appointment_at' => '2026-03-11 08:30:00',
            'reason' => 'Doi lich do benh nhan ban',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 10:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_CARE_TICKET,
        'entity_id' => 303,
        'action' => AuditLog::ACTION_COMPLETE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'care_type' => 'appointment_reminder',
            'care_status_to' => Note::CARE_STATUS_DONE,
        ],
        'occurred_at' => Carbon::parse('2026-03-10 11:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_FACTORY_ORDER,
        'entity_id' => 404,
        'action' => AuditLog::ACTION_COMPLETE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'factory_order_id' => 404,
            'order_no' => 'LABO-404',
            'status_to' => FactoryOrder::STATUS_DELIVERED,
            'supplier_name' => 'Labo Sadec',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 12:00:00'),
    ]);

    $entries = app(PatientOperationalTimelineService::class)->timelineEntriesForPatient($patient, 10);

    expect($entries)->toHaveCount(4)
        ->and($entries->pluck('title')->all())->toBe([
            'Hoàn thành labo',
            'Hoàn thành chăm sóc',
            'Hẹn lại lịch',
            'Cập nhật hóa đơn',
        ])
        ->and($entries->pluck('meta.Nguồn audit')->unique()->all())->toBe(['AuditLog'])
        ->and($entries->pluck('description')->all())->toMatchArray([
            'LABO-404 • Đã giao • Labo Sadec',
            'Nhắc lịch hẹn • Hoàn thành',
            'Lịch hẹn 11/03/2026 08:30 • Đã hẹn lại • Doi lich do benh nhan ban',
            'Hóa đơn INV-001 • 1.250.000đ',
        ]);
});

it('includes insurance claim and treatment session audit entries in the patient operational timeline', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_INSURANCE_CLAIM,
        'entity_id' => 505,
        'action' => AuditLog::ACTION_APPROVE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'claim_number' => 'CLM-505',
            'status_to' => InsuranceClaim::STATUS_APPROVED,
            'amount_approved' => '320000.00',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 13:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_TREATMENT_SESSION,
        'entity_id' => 606,
        'action' => AuditLog::ACTION_COMPLETE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'plan_item_id' => 88,
            'status_to' => 'done',
            'performed_at' => '2026-03-10 12:45:00',
            'reason' => 'Bo sung anh hau thu thuat sau',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 14:00:00'),
    ]);

    $entries = app(PatientOperationalTimelineService::class)->timelineEntriesForPatient($patient, 10);
    $descriptions = $entries->pluck('description')->all();

    expect($entries->pluck('title')->all())->toContain('Bảo hiểm đã duyệt', 'Hoàn thành buổi điều trị')
        ->and($descriptions)->toContain('CLM-505 • Đã duyệt • 320.000đ')
        ->and($descriptions)->toContain('Hạng mục #88 • Hoàn thành • Thực hiện 10/03/2026 12:45 • Bo sung anh hau thu thuat sau');
});

it('includes plan item workflow audit entries in the patient operational timeline', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_PLAN_ITEM,
        'entity_id' => 707,
        'action' => AuditLog::ACTION_UPDATE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'plan_item_id' => 707,
            'plan_item_name' => 'Implant 26',
            'status_to' => PlanItem::STATUS_IN_PROGRESS,
            'required_visits' => 2,
            'completed_visits_to' => 1,
            'reason' => 'Bat dau lam tru implant',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 15:00:00'),
    ]);

    $entries = app(PatientOperationalTimelineService::class)->timelineEntriesForPatient($patient, 10);
    $descriptions = $entries->pluck('description')->all();

    expect($entries->pluck('title')->all())->toContain('Bắt đầu hạng mục điều trị')
        ->and($descriptions)->toContain('Implant 26 • Đang thực hiện • Lần khám 1/2 • Bat dau lam tru implant');
});

it('includes receipt expense workflow audit entries in the patient operational timeline', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_RECEIPT_EXPENSE,
        'entity_id' => 808,
        'action' => AuditLog::ACTION_COMPLETE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'receipt_expense_id' => 808,
            'voucher_code' => 'PTC-808',
            'status_to' => ReceiptExpense::STATUS_POSTED,
            'amount' => 850000,
            'reason' => 'Ket chuyen cuoi ngay',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 16:00:00'),
    ]);

    $entries = app(PatientOperationalTimelineService::class)->timelineEntriesForPatient($patient, 10);
    $descriptions = $entries->pluck('description')->all();

    expect($entries->pluck('title')->all())->toContain('Hạch toán phiếu thu/chi')
        ->and($descriptions)->toContain('Phiếu PTC-808 • 850.000đ');
});

it('formats payment refund audit entries with a finance-friendly description', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_PAYMENT,
        'entity_id' => 909,
        'action' => AuditLog::ACTION_REFUND,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'invoice_id' => 99,
            'invoice_no' => 'INV-909',
            'amount' => -425000,
            'refund_reason' => 'Hoan phan thu thua',
        ],
        'occurred_at' => Carbon::parse('2026-03-10 17:00:00'),
    ]);

    $entries = app(PatientOperationalTimelineService::class)->timelineEntriesForPatient($patient, 10);
    $descriptions = $entries->pluck('description')->all();

    expect($entries->pluck('title')->all())->toContain('Hoàn tiền')
        ->and($descriptions)->toContain('Hóa đơn INV-909 • 425.000đ • Hoan phan thu thua');
});
