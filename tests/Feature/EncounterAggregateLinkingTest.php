<?php

use App\Livewire\PatientExamForm;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\EmrPatientPayloadBuilder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('links new clinical note session to existing appointment encounter by date', function () {
    $branch = Branch::factory()->create();
    $doctor = makeEncounterDoctorForBranch($branch);
    $patient = makeEncounterPatientForBranch($branch);

    $appointmentDate = Carbon::parse('2026-03-01 09:30:00');

    $appointment = Appointment::query()->create([
        'customer_id' => $patient->customer_id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $appointmentDate,
        'duration_minutes' => 45,
        'status' => Appointment::STATUS_SCHEDULED,
        'note' => 'Tao lich hen cho encounter',
    ]);

    $encounter = $appointment->fresh()?->visitEpisode;

    expect($encounter)->not->toBeNull();

    $this->actingAs($doctor);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->set('newSessionDate', $appointmentDate->toDateString())
        ->call('createSession');

    $clinicalNote = $patient->clinicalNotes()
        ->whereDate('date', $appointmentDate->toDateString())
        ->latest('id')
        ->first();

    expect($clinicalNote)->not->toBeNull()
        ->and((int) $clinicalNote?->visit_episode_id)->toBe((int) $encounter?->id);
});

it('creates standalone encounter when clinical note has no matching appointment', function () {
    $branch = Branch::factory()->create();
    $doctor = makeEncounterDoctorForBranch($branch);
    $patient = makeEncounterPatientForBranch($branch);
    $sessionDate = '2026-03-02';

    $this->actingAs($doctor);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->set('newSessionDate', $sessionDate)
        ->call('createSession');

    $clinicalNote = $patient->clinicalNotes()
        ->whereDate('date', $sessionDate)
        ->latest('id')
        ->first();

    $encounter = VisitEpisode::query()->find($clinicalNote?->visit_episode_id);

    expect($clinicalNote)->not->toBeNull()
        ->and($encounter)->not->toBeNull()
        ->and($encounter?->appointment_id)->toBeNull()
        ->and((int) $encounter?->patient_id)->toBe((int) $patient->id)
        ->and((int) $encounter?->branch_id)->toBe((int) $branch->id)
        ->and($encounter?->status)->toBe(VisitEpisode::STATUS_SCHEDULED);
});

it('links prescription to encounter by patient branch and treatment date', function () {
    $branch = Branch::factory()->create();
    $doctor = makeEncounterDoctorForBranch($branch);
    $patient = makeEncounterPatientForBranch($branch);
    $treatmentDate = Carbon::parse('2026-03-03');

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => $treatmentDate->copy()->setTime(8, 30, 0),
        'planned_duration_minutes' => 30,
    ]);

    $prescription = Prescription::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'treatment_date' => $treatmentDate->toDateString(),
        'notes' => 'Kiem thu encounter link',
        'created_by' => $doctor->id,
    ]);

    expect((int) $prescription->visit_episode_id)->toBe((int) $encounter->id)
        ->and((int) $prescription->encounter?->id)->toBe((int) $encounter->id);
});

it('includes encounter records in emr payload', function () {
    $branch = Branch::factory()->create();
    $doctor = makeEncounterDoctorForBranch($branch);
    $patient = makeEncounterPatientForBranch($branch);
    $treatmentDate = Carbon::parse('2026-03-04');

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => $treatmentDate->copy()->setTime(10, 0, 0),
        'in_chair_at' => $treatmentDate->copy()->setTime(10, 10, 0),
        'planned_duration_minutes' => 60,
    ]);

    $prescription = Prescription::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'treatment_date' => $treatmentDate->toDateString(),
        'visit_episode_id' => $encounter->id,
        'notes' => 'Payload encounter',
        'created_by' => $doctor->id,
    ]);

    $payload = app(EmrPatientPayloadBuilder::class)->build($patient->fresh());

    expect(data_get($payload, 'encounter.records'))->toBeArray()
        ->and(data_get($payload, 'encounter.records.0.id'))->toBe((int) $encounter->id)
        ->and(data_get($payload, 'prescription.records.0.visit_episode_id'))->toBe((int) $prescription->visit_episode_id);
});

function makeEncounterDoctorForBranch(Branch $branch): User
{
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->updateOrCreate(
        [
            'user_id' => $doctor->id,
            'branch_id' => $branch->id,
        ],
        [
            'is_active' => true,
            'is_primary' => true,
        ],
    );

    return $doctor;
}

function makeEncounterPatientForBranch(Branch $branch): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
}
