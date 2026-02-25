<?php

use App\Models\Appointment;
use App\Models\AppointmentOverrideLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('marks appointment as emergency with audit log', function () {
    $appointment = makeAppointmentForOverride();
    $actor = User::factory()->create();

    $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_EMERGENCY,
        'Bệnh nhân đau cấp cần xử lý ngay',
        $actor->id,
    );

    $fresh = $appointment->fresh();

    expect($fresh->is_emergency)->toBeTrue()
        ->and($fresh->appointment_type)->toBe('emergency')
        ->and($fresh->operation_override_reason)->toContain('đau cấp')
        ->and($fresh->operation_override_by)->toBe($actor->id);

    $log = AppointmentOverrideLog::query()
        ->where('appointment_id', $appointment->id)
        ->where('override_type', Appointment::OVERRIDE_EMERGENCY)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->reason)->toContain('đau cấp')
        ->and($log->actor_id)->toBe($actor->id);
});

it('marks late arrival and stores late minute context in audit log', function () {
    $appointment = makeAppointmentForOverride();
    $actor = User::factory()->create();

    $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_LATE_ARRIVAL,
        'Bệnh nhân kẹt xe',
        $actor->id,
        ['late_minutes' => 18],
    );

    $fresh = $appointment->fresh();
    $log = AppointmentOverrideLog::query()
        ->where('appointment_id', $appointment->id)
        ->where('override_type', Appointment::OVERRIDE_LATE_ARRIVAL)
        ->latest('id')
        ->first();

    expect($fresh->late_arrival_minutes)->toBe(18)
        ->and($fresh->operation_override_by)->toBe($actor->id)
        ->and($log)->not->toBeNull()
        ->and($log->context['late_minutes'] ?? null)->toBe(18);
});

it('marks walk in appointment with audit log', function () {
    $appointment = makeAppointmentForOverride();
    $actor = User::factory()->create();

    $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_WALK_IN,
        'Khách đến trực tiếp không đặt trước',
        $actor->id,
    );

    $fresh = $appointment->fresh();

    expect($fresh->is_walk_in)->toBeTrue()
        ->and($fresh->appointment_kind)->toBe('booking');
});

it('validates override reason and late minute constraints', function () {
    $appointment = makeAppointmentForOverride();
    $actor = User::factory()->create();

    expect(fn () => $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_EMERGENCY,
        '   ',
        $actor->id,
    ))->toThrow(ValidationException::class, 'Vui lòng nhập lý do override');

    expect(fn () => $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_LATE_ARRIVAL,
        'Có trễ giờ',
        $actor->id,
        ['late_minutes' => 0],
    ))->toThrow(ValidationException::class, 'Số phút trễ phải lớn hơn 0');
});

function makeAppointmentForOverride(array $overrides = []): Appointment
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
        'duration_minutes' => 30,
        'appointment_kind' => 'booking',
        'appointment_type' => 'consultation',
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}
