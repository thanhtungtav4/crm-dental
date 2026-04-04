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
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use App\Models\ReceiptExpense;
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
    $stats = $page->getStats();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->branch_id)->toBe($branchA->id)
        ->and($stats[0]['value'])->toBe('1');
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

it('scopes patient statistical stats to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create(['branch_id' => $branchA->id]);
    $doctorA->assignRole('Doctor');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'primary_doctor_id' => $doctorA->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($manager);

    $page = Livewire::test(PatientStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();

    $records = invokeTableQuery($page)->get();
    $stats = $page->getStats();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->total_patients)->toBe(1)
        ->and($stats)->toBe([
            ['label' => 'Tổng khách hàng', 'value' => '1'],
        ]);
});

it('scopes trick-group aggregates to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $categoryA = ServiceCategory::query()->create([
        'name' => 'Nhóm A',
        'code' => 'nhom-a',
        'active' => true,
    ]);
    $categoryB = ServiceCategory::query()->create([
        'name' => 'Nhóm B',
        'code' => 'nhom-b',
        'active' => true,
    ]);

    $serviceA = Service::query()->create([
        'category_id' => $categoryA->id,
        'name' => 'Thu thuat A',
        'code' => 'tt-a',
        'default_price' => 1_000_000,
        'active' => true,
    ]);

    $serviceB = Service::query()->create([
        'category_id' => $categoryB->id,
        'name' => 'Thu thuat B',
        'code' => 'tt-b',
        'default_price' => 2_000_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'service_id' => $serviceA->id,
        'service_name' => $serviceA->name,
        'category_name' => $categoryA->name,
        'total_count' => 2,
        'total_revenue' => 3_000_000,
        'generated_at' => now(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'service_id' => $serviceB->id,
        'service_name' => $serviceB->name,
        'category_name' => $categoryB->name,
        'total_count' => 7,
        'total_revenue' => 9_000_000,
        'generated_at' => now(),
    ]);

    $this->actingAs($manager);

    $stats = Livewire::test(TrickGroupStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance()
        ->getStats();

    expect($stats[0]['value'])->toBe(number_format(2))
        ->and($stats[1]['value'])->toBe(number_format(3_000_000).' đ');
});

it('scopes revenue expenditure stats to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchA->id,
        'patient_id' => $patientA->id,
        'voucher_code' => 'RE-A',
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'receipt',
        'category_code' => 'service',
        'amount' => 2_500_000,
        'payment_method' => 'transfer',
        'payer_or_receiver' => 'Khach A',
        'content' => 'Thu branch A',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchA->id,
        'patient_id' => $patientA->id,
        'voucher_code' => 'EX-A',
        'voucher_type' => 'expense',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'expense',
        'category_code' => 'ops',
        'amount' => 400_000,
        'payment_method' => 'cash',
        'payer_or_receiver' => 'Vendor A',
        'content' => 'Chi branch A',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchB->id,
        'patient_id' => $patientB->id,
        'voucher_code' => 'RE-B',
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'receipt',
        'category_code' => 'service',
        'amount' => 9_900_000,
        'payment_method' => 'cash',
        'payer_or_receiver' => 'Khach B',
        'content' => 'Thu branch B',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    $this->actingAs($manager);

    $stats = Livewire::test(RevenueExpenditure::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance()
        ->getStats();

    expect($stats)->toBe([
        ['label' => 'Tổng thu', 'value' => '2,500,000 đ'],
        ['label' => 'Tổng chi', 'value' => '400,000 đ'],
        ['label' => 'Biến động', 'value' => '2,100,000 đ'],
    ]);
});

it('scopes material report stats to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    Material::query()->create([
        'branch_id' => $branchA->id,
        'name' => 'Vat tu A',
        'sku' => 'VT-A',
        'unit' => 'hop',
        'stock_qty' => 2,
        'min_stock' => 5,
        'category' => 'consumable',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Material::query()->create([
        'branch_id' => $branchB->id,
        'name' => 'Vat tu B',
        'sku' => 'VT-B',
        'unit' => 'hop',
        'stock_qty' => 10,
        'min_stock' => 5,
        'category' => 'consumable',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($manager);

    $page = Livewire::test(MaterialStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();

    $records = invokeTableQuery($page)->get();
    $stats = $page->getStats();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->branch_id)->toBe($branchA->id)
        ->and($stats)->toBe([
            ['label' => 'Tổng vật tư', 'value' => '1'],
            ['label' => 'Vật tư dưới định mức', 'value' => '1'],
        ]);
});

it('scopes owed report rows and stats to accessible branches when no branch filter is selected', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-SCOPE-A',
        'subtotal' => 2_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 2_000_000,
        'paid_amount' => 1_250_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now(),
    ]);

    Invoice::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-SCOPE-B',
        'subtotal' => 5_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 5_000_000,
        'paid_amount' => 250_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now(),
    ]);

    $this->actingAs($manager);

    $page = Livewire::test(OwedStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();

    $records = invokeTableQuery($page)->get();
    $stats = $page->getStats();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->branch_id)->toBe($branchA->id)
        ->and($stats)->toBe([
            ['label' => 'Tổng phải thanh toán', 'value' => '2,000,000 đ'],
            ['label' => 'Đã thanh toán', 'value' => '1,250,000 đ'],
            ['label' => 'Công nợ', 'value' => '750,000 đ'],
        ]);
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

it('uses the shared branch filter state for risk dashboard queries', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $admin->assignRole('Admin');

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
        'no_show_risk_score' => 61,
        'churn_risk_score' => 44,
        'risk_level' => PatientRiskProfile::LEVEL_MEDIUM,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patientB->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 83,
        'churn_risk_score' => 75,
        'risk_level' => PatientRiskProfile::LEVEL_HIGH,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    $this->actingAs($admin);

    $page = Livewire::test(RiskScoringDashboard::class)
        ->set('tableFilters.branch_id.value', $branchB->id)
        ->instance();

    $records = invokeTableQuery($page)->get();

    expect($records)->toHaveCount(1)
        ->and((int) $records->first()->patient_id)->toBe($patientB->id);
});

function invokeTableQuery(object $page)
{
    $method = new \ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);

    return $method->invoke($page);
}
