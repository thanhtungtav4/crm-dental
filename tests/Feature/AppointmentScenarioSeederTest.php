<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\AppointmentScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

it('creates appointment scenarios for overbooking approval and future temporal guards', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.ident.test')->firstOrFail();
    $this->actingAs($manager);

    $baseAppointment = Appointment::query()
        ->where('note', AppointmentScenarioSeeder::BASE_APPOINTMENT_NOTE)
        ->firstOrFail();
    $futureGuardAppointment = Appointment::query()
        ->where('note', AppointmentScenarioSeeder::FUTURE_GUARD_APPOINTMENT_NOTE)
        ->firstOrFail();
    $overbookPatient = Patient::query()
        ->where('patient_code', AppointmentScenarioSeeder::OVERBOOK_PATIENT_CODE)
        ->firstOrFail();

    $overbooked = Appointment::query()->create([
        'customer_id' => $overbookPatient->customer_id,
        'patient_id' => $overbookPatient->id,
        'doctor_id' => $baseAppointment->doctor_id,
        'assigned_to' => $manager->id,
        'branch_id' => $baseAppointment->branch_id,
        'date' => $baseAppointment->date->copy()->addMinutes(10),
        'appointment_type' => 'consultation',
        'appointment_kind' => 'booking',
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
        'note' => 'seed:appointment-scenario:overlap-created',
        'chief_complaint' => 'Overbooking smoke',
        'overbooking_reason' => 'Khach VIP can chen lich',
        'reminder_hours' => 24,
    ]);

    expect($overbooked->is_overbooked)->toBeTrue()
        ->and($overbooked->overbooking_reason)->toContain('VIP');

    expect(function () use ($futureGuardAppointment): void {
        Appointment::runWithinManagedWorkflow(function () use ($futureGuardAppointment): void {
            $futureGuardAppointment->forceFill([
                'status' => Appointment::STATUS_COMPLETED,
            ])->save();
        });
    })->toThrow(
        ValidationException::class,
        'Không thể cập nhật trạng thái hoàn thành hoặc không đến cho lịch hẹn chưa diễn ra.',
    );

    expect(function () use ($futureGuardAppointment): void {
        Appointment::runWithinManagedWorkflow(function () use ($futureGuardAppointment): void {
            $futureGuardAppointment->fresh()->forceFill([
                'status' => Appointment::STATUS_NO_SHOW,
            ])->save();
        });
    })->toThrow(
        ValidationException::class,
        'Không thể cập nhật trạng thái hoàn thành hoặc không đến cho lịch hẹn chưa diễn ra.',
    );
});
