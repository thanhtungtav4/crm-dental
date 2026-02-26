<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks overbooking when branch policy is disabled', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentContext();

    Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect(fn () => Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 10),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
        'overbooking_reason' => 'Ca đau cấp cần chen lịch',
    ]))->toThrow(ValidationException::class, 'chưa bật overbooking');
});

it('requires overbooking reason and allows with override when policy enabled', function () {
    [$branch, $doctor, $customer, $patient] = makeAppointmentContext();

    BranchOverbookingPolicy::query()->updateOrCreate(
        ['branch_id' => $branch->id],
        [
            'is_enabled' => true,
            'max_parallel_per_doctor' => 1,
            'require_override_reason' => true,
        ],
    );

    Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect(fn () => Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 10),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class, 'lý do override');

    $overbooked = Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(10, 10),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
        'overbooking_reason' => 'Khách VIP cần chen lịch',
    ]);

    expect($overbooked->is_overbooked)->toBeTrue()
        ->and($overbooked->overbooking_reason)->toContain('VIP');
});

function makeAppointmentContext(): array
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

    return [$branch, $doctor, $customer, $patient];
}
