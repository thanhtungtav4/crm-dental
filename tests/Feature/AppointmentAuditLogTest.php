<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;

it('records audit log when appointment is rescheduled', function () {
    $appointment = makeAppointmentForAudit();
    $user = User::factory()->create([
        'branch_id' => $appointment->branch_id,
    ]);

    $this->actingAs($user);

    $appointment->update([
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Bệnh nhân xin dời lịch',
    ]);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_APPOINTMENT)
        ->where('entity_id', $appointment->id)
        ->where('action', AuditLog::ACTION_RESCHEDULE)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['patient_id'] ?? null)->toBe($appointment->patient_id)
        ->and($log->metadata['status_from'] ?? null)->toBe(Appointment::STATUS_SCHEDULED)
        ->and($log->metadata['status_to'] ?? null)->toBe(Appointment::STATUS_RESCHEDULED);
});

function makeAppointmentForAudit(array $overrides = []): Appointment
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

    return Appointment::create(array_merge([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}
