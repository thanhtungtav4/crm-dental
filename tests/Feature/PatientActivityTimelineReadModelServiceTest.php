<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\PatientActivityTimelineReadModelService;
use Carbon\Carbon;

it('builds a unified patient activity timeline from primary resources and audit sources', function (): void {
    $branch = Branch::factory()->create([
        'name' => 'Quận 1',
    ]);
    $otherBranch = Branch::factory()->create([
        'name' => 'Cầu Giấy',
    ]);
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'BS Minh',
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Nguyen Van A',
        'phone' => '0909123456',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => Carbon::parse('2026-03-25 09:00:00'),
        'status' => Appointment::STATUS_CONFIRMED,
        'chief_complaint' => 'Dau rang ham duoi',
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'title' => 'Implant 46',
        'total_cost' => 12500000,
        'created_at' => Carbon::parse('2026-03-25 10:00:00'),
        'updated_at' => Carbon::parse('2026-03-25 10:00:00'),
    ]);

    $invoice = Invoice::factory()->create([
        'treatment_session_id' => null,
        'treatment_plan_id' => $plan->id,
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'invoice_no' => 'INV-READMODEL-001',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 2500000,
        'paid_amount' => 1000000,
        'issued_at' => Carbon::parse('2026-03-25 11:00:00'),
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 1000000,
        'method' => 'transfer',
        'transaction_ref' => 'TXN-001',
        'paid_at' => Carbon::parse('2026-03-25 12:00:00'),
    ]);

    Note::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $doctor->id,
        'type' => Note::TYPE_GENERAL,
        'content' => 'Hen benh nhan quay lai tai kham sau 7 ngay',
        'created_at' => Carbon::parse('2026-03-25 13:00:00'),
        'updated_at' => Carbon::parse('2026-03-25 13:00:00'),
    ]);

    BranchLog::factory()->create([
        'patient_id' => $patient->id,
        'from_branch_id' => $branch->id,
        'to_branch_id' => $otherBranch->id,
        'moved_by' => $doctor->id,
        'note' => 'Dieu phoi sang chi nhanh gan nha',
        'created_at' => Carbon::parse('2026-03-25 14:00:00'),
        'updated_at' => Carbon::parse('2026-03-25 14:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_CONSENT,
        'entity_id' => 501,
        'action' => AuditLog::ACTION_APPROVE,
        'actor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'consent_type' => 'imaging',
            'status_to' => 'signed',
            'consent_version' => 3,
        ],
        'occurred_at' => Carbon::parse('2026-03-25 15:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_APPOINTMENT,
        'entity_id' => 601,
        'action' => AuditLog::ACTION_RESCHEDULE,
        'actor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'status_to' => Appointment::STATUS_RESCHEDULED,
            'appointment_at' => '2026-03-26 09:30:00',
            'reason' => 'Bac si doi lich',
        ],
        'occurred_at' => Carbon::parse('2026-03-25 16:00:00'),
    ]);

    $entries = app(PatientActivityTimelineReadModelService::class)->timelineEntriesForPatient($patient, 10);

    expect($entries->count())->toBeGreaterThanOrEqual(8)
        ->and($entries->pluck('title')->all())->toContain(
            'Hẹn lại lịch',
            'Consent đã ký',
            'Chuyển chi nhánh',
            'Ghi chú',
            'Thanh toán',
            'Hóa đơn',
            'Kế hoạch điều trị',
            'Lịch hẹn',
        )
        ->and($entries->pluck('type')->all())->toContain(
            'audit',
            'clinical_audit',
            'branch_log',
            'note',
            'payment',
            'invoice',
            'treatment_plan',
            'appointment',
        );
});

it('applies the final merged limit after combining patient timeline sources', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    Note::factory()->count(4)->sequence(
        ['patient_id' => $patient->id, 'customer_id' => $customer->id, 'user_id' => $doctor->id, 'content' => 'Note 1', 'created_at' => Carbon::parse('2026-03-25 08:00:00'), 'updated_at' => Carbon::parse('2026-03-25 08:00:00')],
        ['patient_id' => $patient->id, 'customer_id' => $customer->id, 'user_id' => $doctor->id, 'content' => 'Note 2', 'created_at' => Carbon::parse('2026-03-25 09:00:00'), 'updated_at' => Carbon::parse('2026-03-25 09:00:00')],
        ['patient_id' => $patient->id, 'customer_id' => $customer->id, 'user_id' => $doctor->id, 'content' => 'Note 3', 'created_at' => Carbon::parse('2026-03-25 10:00:00'), 'updated_at' => Carbon::parse('2026-03-25 10:00:00')],
        ['patient_id' => $patient->id, 'customer_id' => $customer->id, 'user_id' => $doctor->id, 'content' => 'Note 4', 'created_at' => Carbon::parse('2026-03-25 11:00:00'), 'updated_at' => Carbon::parse('2026-03-25 11:00:00')],
    )->create();

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_CONSENT,
        'entity_id' => 777,
        'action' => AuditLog::ACTION_APPROVE,
        'actor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'consent_type' => 'treatment',
            'status_to' => 'signed',
        ],
        'occurred_at' => Carbon::parse('2026-03-25 12:00:00'),
    ]);

    $entries = app(PatientActivityTimelineReadModelService::class)->timelineEntriesForPatient($patient, 3);

    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('title')->all())->toBe([
            'Consent đã ký',
            'Ghi chú',
            'Ghi chú',
        ])
        ->and($entries->pluck('description')->all())->toBe([
            'treatment • signed',
            'Note 4',
            'Note 3',
        ]);
});
