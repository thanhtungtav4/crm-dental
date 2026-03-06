<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\AppointmentSearchService;

it('finds customer options by encrypted phone and email search hashes', function () {
    $branch = Branch::factory()->create(['active' => true]);

    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($actor);

    $customer = Customer::query()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Lead Search Target',
        'phone' => '0901234567',
        'email' => 'lead-search@example.test',
        'source' => 'walkin',
        'status' => 'lead',
    ]);

    $service = app(AppointmentSearchService::class);

    expect($service->customerOptionsForSearch('0901234567'))
        ->toHaveKey($customer->id)
        ->and($service->customerOptionsForSearch('lead-search@example.test'))
        ->toHaveKey($customer->id);
});

it('applies appointment participant search to lead bookings by encrypted customer fields', function () {
    $branch = Branch::factory()->create(['active' => true]);

    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($actor);

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::query()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Lead Booking Search',
        'phone' => '0909876543',
        'email' => 'lead-booking@example.test',
        'source' => 'walkin',
        'status' => 'lead',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(14, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $service = app(AppointmentSearchService::class);

    $byPhone = $service->applyAppointmentParticipantSearch(
        Appointment::query(),
        '0909876543',
    )->pluck('id')->all();

    $byEmail = $service->applyAppointmentParticipantSearch(
        Appointment::query(),
        'lead-booking@example.test',
    )->pluck('id')->all();

    expect($byPhone)->toContain($appointment->id)
        ->and($byEmail)->toContain($appointment->id);
});
