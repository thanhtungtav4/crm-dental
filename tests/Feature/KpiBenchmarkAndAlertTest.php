<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\OperationalKpiAlert;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\ReportSnapshot;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\VisitEpisode;

it('builds doctor benchmark and triggers kpi alerts with owner and new status', function () {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0988000001',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0988000001',
    ]);

    $appointments = Appointment::factory()->count(3)->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->subHour(),
        'status' => Appointment::STATUS_NO_SHOW,
    ]);

    VisitEpisode::query()->updateOrCreate(
        ['appointment_id' => $appointments->first()->id],
        [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'scheduled_at' => now()->subHour(),
            'planned_duration_minutes' => 120,
            'chair_minutes' => 30,
            'status' => VisitEpisode::STATUS_COMPLETED,
        ]
    );

    $this->actingAs($manager);

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_id', $branch->id)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and(data_get($snapshot?->payload, 'doctor_benchmark.0.doctor_id'))->toBe($doctor->id)
        ->and((float) data_get($snapshot?->payload, 'doctor_benchmark.0.no_show_rate'))->toBeGreaterThan(0);

    $alerts = OperationalKpiAlert::query()
        ->where('snapshot_id', $snapshot?->id)
        ->get();

    expect($alerts->count())->toBeGreaterThanOrEqual(3)
        ->and($alerts->every(fn (OperationalKpiAlert $alert) => $alert->status === OperationalKpiAlert::STATUS_NEW))->toBeTrue()
        ->and($alerts->every(fn (OperationalKpiAlert $alert) => (int) $alert->owner_user_id === $manager->id))->toBeTrue();
});

it('auto resolves kpi alerts when metrics are back in threshold', function () {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0988000011',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0988000011',
    ]);

    $appointments = Appointment::factory()->count(2)->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->subMinutes(30),
        'status' => Appointment::STATUS_NO_SHOW,
    ]);

    VisitEpisode::query()->updateOrCreate(
        ['appointment_id' => $appointments->first()->id],
        [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'scheduled_at' => now()->subMinutes(30),
            'planned_duration_minutes' => 100,
            'chair_minutes' => 20,
            'status' => VisitEpisode::STATUS_COMPLETED,
        ]
    );

    $this->actingAs($manager);

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_id', $branch->id)
        ->firstOrFail();

    expect(OperationalKpiAlert::query()
        ->where('snapshot_id', $snapshot->id)
        ->whereIn('status', [OperationalKpiAlert::STATUS_NEW, OperationalKpiAlert::STATUS_ACK])
        ->count())->toBeGreaterThan(0);

    Appointment::query()
        ->where('branch_id', $branch->id)
        ->update(['status' => Appointment::STATUS_CONFIRMED]);

    expect(Appointment::query()
        ->where('branch_id', $branch->id)
        ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))
        ->count())->toBe(0);

    expect(Appointment::query()
        ->where('branch_id', $branch->id)
        ->whereBetween('date', [now()->startOfDay(), now()->endOfDay()])
        ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))
        ->count())->toBe(0);

    VisitEpisode::query()
        ->where('branch_id', $branch->id)
        ->update([
            'planned_duration_minutes' => 100,
            'chair_minutes' => 100,
        ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị chuẩn',
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ClinicSetting::setValue('report.kpi_no_show_rate_max', 100, [
        'group' => 'report',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('report.kpi_chair_utilization_rate_min', 0, [
        'group' => 'report',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('report.kpi_treatment_acceptance_rate_min', 0, [
        'group' => 'report',
        'value_type' => 'integer',
    ]);

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $matchedSnapshots = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_id', $branch->id)
        ->latest('id')
        ->get();

    expect($matchedSnapshots->count())->toBe(1);

    $snapshot = $matchedSnapshots->firstOrFail();

    expect((float) data_get($snapshot->payload, 'no_show_rate'))->toEqualWithDelta(0.0, 0.01)
        ->and((float) data_get($snapshot->payload, 'chair_utilization_rate'))->toBeGreaterThanOrEqual(99.9)
        ->and((float) data_get($snapshot->payload, 'treatment_acceptance_rate'))->toBeGreaterThanOrEqual(99.9);

    expect(OperationalKpiAlert::query()
        ->where('snapshot_id', $snapshot->id)
        ->whereIn('status', [OperationalKpiAlert::STATUS_NEW, OperationalKpiAlert::STATUS_ACK])
        ->count())->toBe(0);

    expect(OperationalKpiAlert::query()
        ->where('snapshot_id', $snapshot->id)
        ->where('status', OperationalKpiAlert::STATUS_RESOLVED)
        ->count())->toBeGreaterThan(0);
});
