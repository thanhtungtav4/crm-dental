<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use Carbon\Carbon;

it('creates one visit episode when appointment is created', function () {
    $appointment = makeAppointmentForVisitEpisode([
        'status' => Appointment::STATUS_SCHEDULED,
        'duration_minutes' => 45,
    ]);

    $episode = VisitEpisode::query()
        ->where('appointment_id', $appointment->id)
        ->first();

    expect($episode)->not->toBeNull()
        ->and($episode->status)->toBe(VisitEpisode::STATUS_SCHEDULED)
        ->and($episode->planned_duration_minutes)->toBe(45)
        ->and($episode->patient_id)->toBe($appointment->patient_id)
        ->and($episode->doctor_id)->toBe($appointment->doctor_id);
});

it('captures waiting, chair and overrun durations when appointment is completed', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-01 08:00:00'));

    $appointment = makeAppointmentForVisitEpisode([
        'status' => Appointment::STATUS_SCHEDULED,
        'duration_minutes' => 30,
        'date' => Carbon::parse('2026-03-01 09:00:00'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-01 08:50:00'));
    $appointment->update([
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00'));
    $appointment->update([
        'status' => Appointment::STATUS_IN_PROGRESS,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-01 09:40:00'));
    $appointment->update([
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    $episode = $appointment->fresh()->visitEpisode;

    expect($episode)->not->toBeNull()
        ->and($episode->status)->toBe(VisitEpisode::STATUS_COMPLETED)
        ->and($episode->waiting_minutes)->toBe(10)
        ->and($episode->chair_minutes)->toBe(40)
        ->and($episode->actual_duration_minutes)->toBe(40)
        ->and($episode->overrun_minutes)->toBe(10)
        ->and($episode->check_in_at?->format('Y-m-d H:i:s'))->toBe('2026-03-01 08:50:00')
        ->and($episode->in_chair_at?->format('Y-m-d H:i:s'))->toBe('2026-03-01 09:00:00')
        ->and($episode->check_out_at?->format('Y-m-d H:i:s'))->toBe('2026-03-01 09:40:00');

    Carbon::setTestNow();
});

it('keeps episode open metrics empty when appointment is marked no_show', function () {
    $appointment = makeAppointmentForVisitEpisode([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $appointment->update([
        'status' => Appointment::STATUS_NO_SHOW,
    ]);

    $episode = $appointment->fresh()->visitEpisode;

    expect($episode)->not->toBeNull()
        ->and($episode->status)->toBe(VisitEpisode::STATUS_NO_SHOW)
        ->and($episode->waiting_minutes)->toBeNull()
        ->and($episode->chair_minutes)->toBeNull()
        ->and($episode->overrun_minutes)->toBeNull()
        ->and($episode->check_out_at)->toBeNull();
});

function makeAppointmentForVisitEpisode(array $overrides = []): Appointment
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
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}
