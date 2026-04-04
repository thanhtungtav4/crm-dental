<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientAppointmentQuickActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates appointments for an existing patient through the quick action service', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => true,
        'assigned_from' => now()->subDay()->toDateString(),
        'assigned_until' => null,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $this->actingAs($admin);

    $appointment = app(PatientAppointmentQuickActionService::class)->createForPatient($patient, [
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(8, 30)->format('Y-m-d H:i:s'),
        'appointment_kind' => 'booking',
        'status' => Appointment::STATUS_SCHEDULED,
        'note' => 'Quick action patient service',
    ]);

    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->customer_id)->toBeNull()
        ->and($appointment->branch_id)->toBe($branch->id)
        ->and($appointment->doctor_id)->toBe($doctor->id)
        ->and($appointment->note)->toBe('Quick action patient service');
});

it('converts a customer and creates an appointment through the quick action service', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => true,
        'assigned_from' => now()->subDay()->toDateString(),
        'assigned_until' => null,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'lead',
    ]);

    $this->actingAs($admin);

    $appointment = app(PatientAppointmentQuickActionService::class)->createForCustomer($customer, [
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 45)->format('Y-m-d H:i:s'),
        'appointment_kind' => 'booking',
        'status' => Appointment::STATUS_SCHEDULED,
        'note' => 'Quick action customer service',
    ]);

    $patient = Patient::query()
        ->where('customer_id', $customer->id)
        ->firstOrFail();

    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->status)->toBe('converted');
});

it('rejects quick action appointments outside the actor branch scope', function (): void {
    $allowedBranch = Branch::factory()->create();
    $hiddenBranch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $allowedBranch->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $allowedBranch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $allowedBranch->id,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(PatientAppointmentQuickActionService::class)->createForPatient($patient, [
        'branch_id' => $hiddenBranch->id,
        'date' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        'appointment_kind' => 'booking',
        'status' => Appointment::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class);
});
