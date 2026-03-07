<?php

use App\Filament\Pages\Reports\AppointmentStatistical;
use App\Filament\Pages\Reports\MaterialStatistical;
use App\Filament\Pages\Reports\OperationalKpiPack;
use App\Filament\Pages\Reports\OwedStatistical;
use App\Filament\Pages\Reports\PatientStatistical;
use App\Filament\Pages\Reports\RevenueExpenditure;
use App\Filament\Pages\Reports\RevenueStatistical;
use App\Filament\Pages\Reports\RiskScoringDashboard;
use App\Filament\Pages\Reports\TrickGroupStatistical;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\ReportSnapshot;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Livewire\Livewire;

dataset('restrictedKpiPages', [
    AppointmentStatistical::class,
    RevenueStatistical::class,
    OperationalKpiPack::class,
    RiskScoringDashboard::class,
    PatientStatistical::class,
    OwedStatistical::class,
    RevenueExpenditure::class,
    MaterialStatistical::class,
    TrickGroupStatistical::class,
]);

it('blocks doctors from accessing sensitive KPI report pages', function (string $pageClass): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get($pageClass::getUrl())
        ->assertForbidden();
})->with('restrictedKpiPages');

it('scopes appointment statistical records to accessible branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'date' => now()->setTime(9, 0),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'date' => now()->setTime(10, 0),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $this->actingAs($manager);

    $page = Livewire::test(AppointmentStatistical::class)->instance();
    $records = invokeTableQuery($page)->get();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->branch_id)->toBe($branchA->id);
});

it('scopes revenue statistical aggregates to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $category = ServiceCategory::query()->create([
        'name' => 'Tong hop',
        'code' => 'tong-hop',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Thu thuat test',
        'code' => 'tt-test',
        'default_price' => 1_000_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 2,
        'total_revenue' => 3_000_000,
        'generated_at' => now(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 7,
        'total_revenue' => 9_000_000,
        'generated_at' => now(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 9,
        'total_revenue' => 12_000_000,
        'generated_at' => now(),
    ]);

    $this->actingAs($manager);

    $stats = Livewire::test(RevenueStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance()
        ->getStats();

    expect($stats[0]['value'])->toBe(number_format(2))
        ->and($stats[1]['value'])->toBe(number_format(3_000_000).' đ');
});

it('hides inaccessible KPI snapshots when a non-admin forges another branch filter', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => ['booking_to_visit_rate' => 22.5],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => ['booking_to_visit_rate' => 88.8],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    $this->actingAs($manager);

    $stats = Livewire::test(OperationalKpiPack::class)
        ->set('tableFilters.branch_id.value', $branchB->id)
        ->instance()
        ->getStats();

    expect($stats[0]['value'])->toBe('Chưa có');
});

it('scopes risk dashboard profiles to accessible branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patientA->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 65,
        'churn_risk_score' => 55,
        'risk_level' => PatientRiskProfile::LEVEL_MEDIUM,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patientB->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 92,
        'churn_risk_score' => 87,
        'risk_level' => PatientRiskProfile::LEVEL_HIGH,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    $this->actingAs($manager);

    $page = Livewire::test(RiskScoringDashboard::class)->instance();
    $records = invokeTableQuery($page)->get();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->patient_id)->toBe($patientA->id);
});

function invokeTableQuery(object $page)
{
    $method = new \ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);

    return $method->invoke($page);
}
