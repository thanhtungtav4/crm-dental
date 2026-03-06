<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks users without appointment override permission from creating overbooked appointments', function () {
    [$branch, $doctor, $customer, $patient] = makeOverbookingAuthorizationContext();

    BranchOverbookingPolicy::query()->updateOrCreate(
        ['branch_id' => $branch->id],
        [
            'is_enabled' => true,
            'max_parallel_per_doctor' => 2,
            'require_override_reason' => false,
        ],
    );

    $unauthorizedUser = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($unauthorizedUser);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect(fn () => Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 10),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class, 'không có quyền override vận hành lịch hẹn');
});

it('allows users with appointment override permission to create overbooked appointments within branch policy', function () {
    [$branch, $doctor, $customer, $patient] = makeOverbookingAuthorizationContext();

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

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $overbooked = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 10),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect($overbooked->is_overbooked)->toBeTrue()
        ->and((int) $overbooked->overbooking_override_by)->toBe($manager->id);
});

function makeOverbookingAuthorizationContext(): array
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
