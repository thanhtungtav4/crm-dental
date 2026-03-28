<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentSchedulingService;
use Illuminate\Validation\ValidationException;

it('creates appointments through the shared scheduling service', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $appointment = app(AppointmentSchedulingService::class)->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect($appointment->exists)->toBeTrue()
        ->and((int) $appointment->branch_id)->toBe($branch->id)
        ->and((int) $appointment->doctor_id)->toBe($doctor->id);
});

it('blocks calendar-style reschedule conflicts unless force is requested', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    BranchOverbookingPolicy::query()->updateOrCreate(
        ['branch_id' => $branch->id],
        [
            'is_enabled' => true,
            'max_parallel_per_doctor' => 2,
            'require_override_reason' => false,
        ],
    );

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $target = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 15),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    expect(fn () => app(AppointmentSchedulingService::class)->reschedule(
        appointment: $target,
        startAt: now()->addDay()->setTime(9, 20),
        force: false,
        reason: 'Khach xin doi lich do trung gio cong viec',
    ))->toThrow(ValidationException::class, 'Khung giờ bị trùng lịch');
});

it('allows force reschedule through scheduling service for authorized override actors', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    BranchOverbookingPolicy::query()->updateOrCreate(
        ['branch_id' => $branch->id],
        [
            'is_enabled' => true,
            'max_parallel_per_doctor' => 2,
            'require_override_reason' => false,
        ],
    );

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $target = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 15),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $this->actingAs($manager);

    $rescheduled = app(AppointmentSchedulingService::class)->reschedule(
        appointment: $target,
        startAt: now()->addDay()->setTime(9, 20),
        force: true,
        reason: 'Can chen lich khan de giu slot cho benh nhan',
    );

    expect($rescheduled->date?->format('H:i'))->toBe('09:20')
        ->and($rescheduled->is_overbooked)->toBeTrue()
        ->and((string) $rescheduled->overbooking_reason)->toContain('Override');
});

it('requires a reason and records audit metadata when rescheduling appointments', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(15, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(AppointmentSchedulingService::class)->reschedule(
        appointment: $appointment,
        startAt: now()->addDay()->setTime(15, 30),
        force: false,
        reason: '   ',
    ))->toThrow(ValidationException::class, 'Vui lòng nhập lý do đổi lịch');

    $rescheduled = app(AppointmentSchedulingService::class)->reschedule(
        appointment: $appointment,
        startAt: now()->addDay()->setTime(15, 30),
        force: false,
        reason: 'Khach xin doi sang cuoi gio chieu',
    );

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_APPOINTMENT)
        ->where('entity_id', $appointment->id)
        ->where('action', AuditLog::ACTION_RESCHEDULE)
        ->latest('id')
        ->first();

    expect($rescheduled->date?->format('H:i'))->toBe('15:30')
        ->and($rescheduled->status)->toBe(Appointment::STATUS_RESCHEDULED)
        ->and($rescheduled->reschedule_reason)->toBe('Khach xin doi sang cuoi gio chieu')
        ->and($auditLog)->not->toBeNull()
        ->and(data_get($auditLog?->metadata, 'status_from'))->toBe(Appointment::STATUS_SCHEDULED)
        ->and(data_get($auditLog?->metadata, 'status_to'))->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(data_get($auditLog?->metadata, 'from_at'))->toContain('15:00:00')
        ->and(data_get($auditLog?->metadata, 'to_at'))->toContain('15:30:00')
        ->and(data_get($auditLog?->metadata, 'reason'))->toBe('Khach xin doi sang cuoi gio chieu')
        ->and(data_get($auditLog?->metadata, 'trigger'))->toBe('manual_reschedule')
        ->and(data_get($auditLog?->metadata, 'source'))->toBe('calendar');
});

it('requires a reason and records form audit metadata when updating appointment slot', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(AppointmentSchedulingService::class)->update($appointment, [
        'date' => now()->addDay()->setTime(10, 45),
    ]))->toThrow(ValidationException::class, 'Vui lòng nhập lý do đổi lịch');

    $updated = app(AppointmentSchedulingService::class)->update($appointment, [
        'date' => now()->addDay()->setTime(10, 45),
        'reschedule_reason' => 'Benh nhan xin doi sang ca muon hon',
    ]);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_APPOINTMENT)
        ->where('entity_id', $appointment->id)
        ->where('action', AuditLog::ACTION_RESCHEDULE)
        ->latest('id')
        ->first();

    expect($updated->date?->format('H:i'))->toBe('10:45')
        ->and($updated->status)->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(data_get($auditLog?->metadata, 'status_from'))->toBe(Appointment::STATUS_CONFIRMED)
        ->and(data_get($auditLog?->metadata, 'status_to'))->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(data_get($auditLog?->metadata, 'trigger'))->toBe('manual_reschedule')
        ->and(data_get($auditLog?->metadata, 'source'))->toBe('form')
        ->and(data_get($auditLog?->metadata, 'reason'))->toBe('Benh nhan xin doi sang ca muon hon');
});

it('records reschedule audit when moving a slot that is already marked rescheduled', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->subHour()->setTime(10, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Da doi lich lan truoc',
    ]);

    $this->actingAs($manager);

    $updated = app(AppointmentSchedulingService::class)->update($appointment, [
        'date' => now()->subHour()->setTime(10, 30),
        'reschedule_reason' => 'Benh nhan doi them 30 phut',
    ]);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_APPOINTMENT)
        ->where('entity_id', $appointment->id)
        ->where('action', AuditLog::ACTION_RESCHEDULE)
        ->latest('id')
        ->first();

    expect($updated->date?->format('H:i'))->toBe('10:30')
        ->and($updated->status)->toBe(Appointment::STATUS_RESCHEDULED)
        ->and($auditLog)->not->toBeNull()
        ->and(data_get($auditLog?->metadata, 'status_from'))->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(data_get($auditLog?->metadata, 'status_to'))->toBe(Appointment::STATUS_RESCHEDULED)
        ->and(data_get($auditLog?->metadata, 'from_at'))->toContain('10:00:00')
        ->and(data_get($auditLog?->metadata, 'to_at'))->toContain('10:30:00')
        ->and(data_get($auditLog?->metadata, 'source'))->toBe('form')
        ->and(data_get($auditLog?->metadata, 'reason'))->toBe('Benh nhan doi them 30 phut');
});

it('transitions appointment status through the scheduling service with guided payloads', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(11, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $this->actingAs($manager);

    $confirmed = app(AppointmentSchedulingService::class)->transitionStatus(
        $appointment,
        Appointment::STATUS_CONFIRMED,
    );

    $cancelled = app(AppointmentSchedulingService::class)->transitionStatus(
        $confirmed,
        Appointment::STATUS_CANCELLED,
        ['reason' => 'Benh nhan xin huy lich'],
    );

    $cancelAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_APPOINTMENT)
        ->where('entity_id', $appointment->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($cancelled->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($cancelled->cancellation_reason)->toBe('Benh nhan xin huy lich')
        ->and(data_get($cancelAudit, 'metadata.status_from'))->toBe(Appointment::STATUS_CONFIRMED)
        ->and(data_get($cancelAudit, 'metadata.status_to'))->toBe(Appointment::STATUS_CANCELLED)
        ->and(data_get($cancelAudit, 'metadata.reason'))->toBe('Benh nhan xin huy lich');
});

it('blocks future appointments from being marked completed or no_show', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentSchedulingContext();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(14, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(AppointmentSchedulingService::class)->transitionStatus(
        $appointment,
        Appointment::STATUS_NO_SHOW,
    ))->toThrow(ValidationException::class, 'chưa diễn ra');

    expect(fn () => app(AppointmentSchedulingService::class)->transitionStatus(
        $appointment->fresh(),
        Appointment::STATUS_COMPLETED,
    ))->toThrow(ValidationException::class, 'chưa diễn ra');
});

function makeAppointmentSchedulingContext(): array
{
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => true,
        'assigned_from' => today()->subDay()->toDateString(),
    ]);

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

    return [$branch, $doctor, $customer, $patient];
}
