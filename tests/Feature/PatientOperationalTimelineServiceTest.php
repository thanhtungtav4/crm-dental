<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FactoryOrder;
use App\Models\Note;
use App\Models\Patient;
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
