<?php

use App\Models\Appointment;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\CareScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;

use function Pest\Laravel\seed;

it('creates care automation scenarios for no-show recovery and reactivation skips', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($admin);

    $noShowAppointment = Appointment::query()
        ->where('note', CareScenarioSeeder::NO_SHOW_APPOINTMENT_NOTE)
        ->firstOrFail();
    $eligiblePatient = Patient::query()
        ->where('patient_code', CareScenarioSeeder::REACTIVATION_PATIENT_CODE)
        ->firstOrFail();
    $blockedPatient = Patient::query()
        ->where('patient_code', CareScenarioSeeder::BLOCKED_REACTIVATION_PATIENT_CODE)
        ->firstOrFail();

    $this->artisan('appointments:run-no-show-recovery', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();
    $this->artisan('appointments:run-no-show-recovery', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(Note::query()
        ->where('source_type', Appointment::class)
        ->where('source_id', $noShowAppointment->id)
        ->where('care_type', 'no_show_recovery')
        ->count())->toBe(1);

    $this->artisan('growth:run-reactivation-flow', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();
    $this->artisan('growth:run-reactivation-flow', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(Note::query()
        ->where('source_type', Patient::class)
        ->where('source_id', $eligiblePatient->id)
        ->where('care_type', 'reactivation_follow_up')
        ->count())->toBe(1)
        ->and(Note::query()
            ->where('source_type', Patient::class)
            ->where('source_id', $blockedPatient->id)
            ->where('care_type', 'reactivation_follow_up')
            ->exists())->toBeFalse();
});
