<?php

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;

it('allows admin to schedule appointments from the patient table through the quick action', function (): void {
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

    $appointmentAt = now()->addDay()->setTime(10, 15)->format('Y-m-d H:i:s');

    $this->actingAs($admin);

    Livewire::test(ListPatients::class)
        ->assertTableActionVisible('createAppointment', $patient)
        ->callTableAction('createAppointment', $patient, [
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'date' => $appointmentAt,
            'appointment_kind' => 'booking',
            'status' => Appointment::STATUS_SCHEDULED,
            'note' => 'Quick action tu patient list.',
        ])
        ->assertHasNoActionErrors();

    $appointment = Appointment::query()
        ->where('patient_id', $patient->id)
        ->where('doctor_id', $doctor->id)
        ->where('branch_id', $branch->id)
        ->firstOrFail();

    expect($appointment->status)->toBe(Appointment::STATUS_SCHEDULED)
        ->and($appointment->customer_id)->toBeNull()
        ->and($appointment->note)->toBe('Quick action tu patient list.');

    $this->actingAs($admin)
        ->get(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'appointments']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertOk();
});
