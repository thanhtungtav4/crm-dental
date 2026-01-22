<?php

use App\Models\Customer;
use App\Models\Patient;

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


