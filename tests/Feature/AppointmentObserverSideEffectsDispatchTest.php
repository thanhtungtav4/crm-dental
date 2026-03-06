<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\SyncAppointmentLifecycleSideEffects;
use Illuminate\Support\Facades\Queue;

it('dispatches one orchestration job when an appointment is created', function (): void {
    Queue::fake();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
    ]);

    Queue::assertPushed(SyncAppointmentLifecycleSideEffects::class, function (SyncAppointmentLifecycleSideEffects $job) use ($appointment): bool {
        return $job->appointmentId === $appointment->id
            && $job->mode === SyncAppointmentLifecycleSideEffects::MODE_UPSERT
            && $job->statusChanged
            && $job->shouldSyncOperationalArtifacts
            && ! $job->shouldAttemptLeadConversion;
    });
});

it('dispatches conversion-aware orchestration when a lead appointment is completed', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'lead',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Queue::fake();

    $appointment->update([
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    Queue::assertPushed(SyncAppointmentLifecycleSideEffects::class, function (SyncAppointmentLifecycleSideEffects $job) use ($appointment): bool {
        return $job->appointmentId === $appointment->id
            && $job->mode === SyncAppointmentLifecycleSideEffects::MODE_UPSERT
            && $job->statusChanged
            && $job->shouldSyncOperationalArtifacts
            && $job->shouldAttemptLeadConversion;
    });
});

it('does not dispatch orchestration job for irrelevant appointment field updates', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Queue::fake();

    $appointment->update([
        'internal_notes' => 'Ghi chú nội bộ không ảnh hưởng scheduling',
    ]);

    Queue::assertNothingPushed();
});

it('dispatches delete orchestration when an appointment is soft deleted', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Queue::fake();

    $appointment->delete();

    Queue::assertPushed(SyncAppointmentLifecycleSideEffects::class, function (SyncAppointmentLifecycleSideEffects $job) use ($appointment): bool {
        return $job->appointmentId === $appointment->id
            && $job->mode === SyncAppointmentLifecycleSideEffects::MODE_DELETED;
    });
});
