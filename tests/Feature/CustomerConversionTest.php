<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientConversionService;

it('converts a customer to a patient and updates status', function () {
    $customer = Customer::factory()->create([
        'status' => 'confirmed',
        'branch_id' => null,
    ]);

    $patient = $customer->convertToPatient();

    expect($patient)->toBeInstanceOf(Patient::class)
        ->and($patient->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->status)->toBe('converted');
});

it('generates patient code in PAT format when converting', function () {
    $customer = Customer::factory()->create([
        'status' => 'lead',
    ]);

    $patient = $customer->convertToPatient();

    expect($patient->patient_code)->toMatch('/^PAT-\d{8}-[A-Z0-9]{6}$/');
});

it('reuses the same patient when converting the same customer twice', function () {
    $customer = Customer::factory()->create([
        'status' => 'lead',
    ]);

    $firstPatient = $customer->convertToPatient();
    $secondPatient = $customer->fresh()->convertToPatient();

    expect($firstPatient->id)->toBe($secondPatient->id)
        ->and(Patient::where('customer_id', $customer->id)->count())->toBe(1);
});

it('dedupes by phone and branch before creating a new patient', function () {
    $branch = Branch::factory()->create();

    $existingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0900111222',
    ]);

    $existingPatient = Patient::factory()->create([
        'customer_id' => $existingCustomer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0900111222',
    ]);

    $incomingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0900111222',
        'status' => 'lead',
    ]);

    $beforeCount = Patient::count();

    $resolvedPatient = app(PatientConversionService::class)->convert($incomingCustomer);

    expect($resolvedPatient)->not->toBeNull()
        ->and($resolvedPatient->id)->toBe($existingPatient->id)
        ->and(Patient::count())->toBe($beforeCount)
        ->and($incomingCustomer->fresh()->status)->toBe('lead');
});

it('links appointment to existing deduped patient during conversion', function () {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $existingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0900333444',
    ]);

    $existingPatient = Patient::factory()->create([
        'customer_id' => $existingCustomer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0900333444',
    ]);

    $incomingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0900333444',
        'status' => 'lead',
    ]);

    $appointment = Appointment::create([
        'customer_id' => $incomingCustomer->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $resolvedPatient = app(PatientConversionService::class)->convert($incomingCustomer, $appointment);

    expect($resolvedPatient)->not->toBeNull()
        ->and($resolvedPatient->id)->toBe($existingPatient->id)
        ->and($appointment->fresh()->patient_id)->toBe($existingPatient->id);
});

it('auto creates a lead customer with valid source when creating patient directly', function () {
    $patient = Patient::create([
        'full_name' => 'Patient Created Directly',
        'phone' => '0900777999',
        'email' => 'direct.patient@example.com',
        'status' => 'active',
    ]);

    $patient->refresh();
    $customer = $patient->customer;

    expect($customer)->not->toBeNull()
        ->and($patient->customer_id)->toBe($customer->id)
        ->and($customer->full_name)->toBe('Patient Created Directly')
        ->and($customer->phone)->toBe('0900777999')
        ->and($customer->source)->toBe('walkin')
        ->and($customer->status)->toBe('lead');
});
