<?php

use App\Models\Appointment;
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
    );

    expect($rescheduled->date?->format('H:i'))->toBe('09:20')
        ->and($rescheduled->is_overbooked)->toBeTrue()
        ->and((string) $rescheduled->overbooking_reason)->toContain('Override');
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
