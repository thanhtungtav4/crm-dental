<?php

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;

it('allows cskh to convert a lead into a patient and open the patient workspace', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'lead',
    ]);

    $this->actingAs($cskh);

    $component = Livewire::test(ListCustomers::class)
        ->assertTableActionVisible('convertToPatient', $customer)
        ->callTableAction('convertToPatient', $customer)
        ->assertHasNoActionErrors();

    $patient = Patient::query()
        ->where('customer_id', $customer->id)
        ->firstOrFail();

    $component->assertRedirect(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'basic-info']));

    Livewire::test(ListCustomers::class)
        ->assertTableActionHidden('convertToPatient', $customer->fresh());

    expect($customer->fresh()->status)->toBe('converted');

    $this->actingAs($cskh)
        ->get(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'basic-info']))
        ->assertOk();
});

it('redirects cskh to the existing patient workspace when conversion reuses a deduped profile', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $existingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0900111222',
        'status' => 'converted',
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

    $this->actingAs($cskh);

    Livewire::test(ListCustomers::class)
        ->assertTableActionVisible('convertToPatient', $incomingCustomer)
        ->callTableAction('convertToPatient', $incomingCustomer)
        ->assertRedirect(PatientResource::getUrl('view', ['record' => $existingPatient, 'tab' => 'basic-info']))
        ->assertHasNoActionErrors();

    expect($incomingCustomer->fresh()->status)->toBe('lead')
        ->and($existingPatient->fresh()->customer_id)->toBe($existingCustomer->id);
});

it('allows cskh to schedule appointments from the customer table and manage the created record', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

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

    $appointmentAt = now()->addDay()->setTime(9, 30)->format('Y-m-d H:i:s');

    $this->actingAs($cskh);

    Livewire::test(ListCustomers::class)
        ->assertTableActionVisible('createAppointment', $customer)
        ->callTableAction('createAppointment', $customer, [
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'date' => $appointmentAt,
            'appointment_kind' => 'booking',
            'status' => Appointment::STATUS_SCHEDULED,
            'note' => 'Front-office booked from QA regression.',
        ])
        ->assertHasNoActionErrors();

    $patient = Patient::query()
        ->where('customer_id', $customer->id)
        ->firstOrFail();

    $appointment = Appointment::query()
        ->where('customer_id', $customer->id)
        ->where('patient_id', $patient->id)
        ->firstOrFail();

    expect($appointment->branch_id)->toBe($branch->id)
        ->and($appointment->doctor_id)->toBe($doctor->id)
        ->and($appointment->status)->toBe(Appointment::STATUS_SCHEDULED);

    $this->actingAs($cskh)
        ->get(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'appointments']))
        ->assertOk();

    $this->actingAs($cskh)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertOk();
});

it('supports frontdesk phone search even when the query contains spaces', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Duplicate QA Lead',
        'phone' => '0909001092',
    ]);

    $this->actingAs($cskh);

    Livewire::test(ListCustomers::class)
        ->searchTable('0909 001 092')
        ->assertCanSeeTableRecords([$customer]);
});
