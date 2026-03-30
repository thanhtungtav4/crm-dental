<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientAppointmentActionReadModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds active appointment quick-action state through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Bac si Le',
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $activeAppointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 30),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->subDay()->setTime(9, 30),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDays(2)->setTime(10, 0),
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => 'Khach doi lich',
    ]);

    $otherCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $otherPatient = Patient::factory()->create([
        'customer_id' => $otherCustomer->id,
        'first_branch_id' => $branch->id,
    ]);

    Appointment::factory()->create([
        'patient_id' => $otherPatient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(11, 0),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $service = app(PatientAppointmentActionReadModelService::class);

    expect($service->hasActiveAppointments($patient))->toBeTrue()
        ->and($service->activeAppointmentOptions($patient))->toBe([
            $activeAppointment->id => sprintf(
                '%s — %s — %s',
                $activeAppointment->date?->format('d/m/Y H:i'),
                'Bac si Le',
                Appointment::statusLabel($activeAppointment->status),
            ),
        ])
        ->and($service->hasActiveAppointments($otherPatient))->toBeTrue();
});
