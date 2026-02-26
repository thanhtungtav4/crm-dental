<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('normalizes legacy status aliases including later/rebooked', function () {
    $appointment = makeAppointmentRecord([
        'status' => 'LATER',
        'reschedule_reason' => 'Bệnh nhân xin đổi lịch',
    ]);

    $aliases = Appointment::statusesForQuery([Appointment::STATUS_RESCHEDULED]);

    expect($appointment->status)->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(Appointment::statusLabel($appointment->status))->toBe('Đã hẹn lại')
        ->and($aliases)->toContain('rescheduled')
        ->and($aliases)->toContain('later')
        ->and($aliases)->toContain('LATER')
        ->and($aliases)->toContain('rebooked')
        ->and($aliases)->toContain('REBOOKED');
});

it('blocks invalid appointment status transition with APPOINTMENT_STATE_INVALID', function () {
    $appointment = makeAppointmentRecord([
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    expect(fn () => $appointment->update([
        'status' => Appointment::STATUS_NO_SHOW,
    ]))->toThrow(ValidationException::class, 'APPOINTMENT_STATE_INVALID');
});

it('requires reason when transitioning to cancelled or rescheduled', function () {
    $appointment = makeAppointmentRecord([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect(fn () => $appointment->update([
        'status' => Appointment::STATUS_CANCELLED,
    ]))->toThrow(ValidationException::class, 'lý do hủy');

    $appointment->update([
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => 'Bệnh nhân bận đột xuất',
    ]);

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CANCELLED);

    $followUp = makeAppointmentRecord([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect(fn () => $followUp->update([
        'status' => Appointment::STATUS_RESCHEDULED,
    ]))->toThrow(ValidationException::class, 'lý do hẹn lại');

    $followUp->update([
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Bệnh nhân dời lịch theo yêu cầu',
    ]);

    expect($followUp->fresh()->status)->toBe(Appointment::STATUS_RESCHEDULED);
});

it('creates appointment reminder ticket for rescheduled and cancels when status recovers', function () {
    $appointment = makeAppointmentRecord([
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Dời lịch vì công tác',
    ]);

    $ticket = Note::query()
        ->where('source_type', Appointment::class)
        ->where('source_id', $appointment->id)
        ->where('care_type', 'appointment_reminder')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(\App\Models\Note::CARE_STATUS_NOT_STARTED)
        ->and($ticket->content)->toContain('Dời lịch vì công tác');

    $appointment->update([
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    expect($ticket->fresh()->care_status)->toBe(\App\Models\Note::CARE_STATUS_FAILED);
});

it('auto links appointment to existing patient when customer is already converted', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'converted',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $appointment = Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect($appointment->patient_id)->toBe($patient->id);
});

it('keeps appointment as lead booking when customer has no patient profile yet', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $leadCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'lead',
    ]);

    $appointment = Appointment::create([
        'customer_id' => $leadCustomer->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect($appointment->patient_id)->toBeNull();
});

function makeAppointmentRecord(array $overrides = []): Appointment
{
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
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    return Appointment::create(array_merge([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}
