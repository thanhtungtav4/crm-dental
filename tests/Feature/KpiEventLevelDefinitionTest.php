<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\OperationalKpiService;
use App\Support\OperationalKpiDictionary;

it('computes booking to visit using arrived visit episodes instead of appointment status only', function () {
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
    ]);

    $arrivedAppointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->setTime(9, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->setTime(10, 0),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    VisitEpisode::query()->updateOrCreate(
        ['appointment_id' => $arrivedAppointment->id],
        [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'status' => VisitEpisode::STATUS_IN_PROGRESS,
            'scheduled_at' => now()->setTime(9, 0),
            'arrived_at' => now()->setTime(9, 5),
            'planned_duration_minutes' => 30,
            'chair_minutes' => 15,
        ],
    );

    $snapshot = app(OperationalKpiService::class)->buildSnapshot(
        now()->startOfDay(),
        now()->endOfDay(),
        $branch->id,
    );

    $metrics = (array) ($snapshot['metrics'] ?? []);

    expect((int) ($metrics['booking_count'] ?? 0))->toBe(2)
        ->and((int) ($metrics['visit_count'] ?? 0))->toBe(1)
        ->and((float) ($metrics['booking_to_visit_rate'] ?? 0))->toEqualWithDelta(50.0, 0.01);
});

it('embeds versioned kpi dictionary in lineage payload', function () {
    $branch = Branch::factory()->create();

    $snapshot = app(OperationalKpiService::class)->buildSnapshot(
        now()->startOfDay(),
        now()->endOfDay(),
        $branch->id,
    );

    $lineage = (array) ($snapshot['lineage'] ?? []);
    $dictionary = (array) data_get($lineage, 'kpi_dictionary', []);

    expect((string) data_get($dictionary, 'version'))->toBe(OperationalKpiDictionary::VERSION)
        ->and((string) data_get($dictionary, 'event_definitions.visit_event'))->toContain('visit_episodes')
        ->and((string) data_get($dictionary, 'metrics.booking_to_visit_rate.formula'))->toContain('visit_count');
});
