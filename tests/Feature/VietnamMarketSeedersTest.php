<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\DoctorBranchAssignment;
use App\Models\FactoryOrder;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Note;
use App\Models\Supplier;
use App\Models\User;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Database\Seeders\AppointmentScenarioSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FinanceScenarioSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\SupplierScenarioSeeder;
use Database\Seeders\ZnsAutomationScenarioSeeder;
use Illuminate\Support\Facades\Hash;

it('preserves existing clinic runtime settings while seeding defaults', function (): void {
    createVietnamTestBranch('HCM-Q1', 'Nha khoa Demo Quan 1');

    ClinicSetting::setValue('zns.access_token', 'existing-secret-token', [
        'group' => 'zns',
        'label' => 'ZNS Access Token',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
        'sort_order' => 120,
    ]);

    $this->seed(ClinicSettingsSeeder::class);

    ClinicSetting::flushRuntimeCache();

    expect(ClinicSetting::getValue('zns.access_token'))->toBe('existing-secret-token')
        ->and(ClinicSetting::getValue('web_lead.default_branch_code'))->toBe('HCM-Q1');
});

it('backfills the default web lead branch code when the setting is blank', function (): void {
    createVietnamTestBranch('HN-CG', 'Nha khoa Demo Cau Giay');

    ClinicSetting::setValue('web_lead.default_branch_code', '', [
        'group' => 'web_lead',
        'label' => 'Chi nhanh mac dinh cho web lead',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 480,
    ]);

    $migration = require database_path('migrations/2026_03_07_121748_backfill_default_web_lead_branch_code_setting.php');
    $migration->up();

    ClinicSetting::flushRuntimeCache();

    expect(ClinicSetting::getValue('web_lead.default_branch_code'))->toBe('HN-CG');
});

it('seeds the inventory catalog for every active branch without creating duplicates', function (): void {
    User::factory()->create();
    $branchQ1 = createVietnamTestBranch('HCM-Q1', 'Nha khoa Demo Quan 1');
    $branchCg = createVietnamTestBranch('HN-CG', 'Nha khoa Demo Cau Giay');

    $this->seed(InventorySeeder::class);

    $initialMaterialCount = Material::query()->count();
    $initialBatchCount = MaterialBatch::query()->count();

    $this->seed(InventorySeeder::class);

    expect(Supplier::query()->count())->toBe(5)
        ->and(Material::query()->where('branch_id', $branchQ1->id)->count())->toBe(25)
        ->and(Material::query()->where('branch_id', $branchCg->id)->count())->toBe(25)
        ->and(Material::query()->count())->toBe($initialMaterialCount)
        ->and(MaterialBatch::query()->count())->toBe($initialBatchCount)
        ->and(Material::query()->where('branch_id', $branchQ1->id)->where('sku', 'MED-001')->exists())->toBeTrue()
        ->and(Material::query()->where('branch_id', $branchCg->id)->where('sku', 'MED-001')->exists())->toBeTrue();
});

it('seeds a deterministic vietnam market baseline through database seeder', function (): void {
    $this->seed(DatabaseSeeder::class);

    ClinicSetting::flushRuntimeCache();

    expect(Branch::query()->whereIn('code', ['HCM-Q1', 'HN-CG', 'DN-HC'])->count())->toBe(3)
        ->and(User::query()->where('email', 'admin@demo.ident.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'doctor.hc@demo.ident.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'automation.bot@demo.ident.test')->exists())->toBeTrue()
        ->and(ClinicSetting::getValue('web_lead.default_branch_code'))->toBe('HCM-Q1');
});

it('seeds the local demo user pack with deterministic roles and cross-branch doctor access', function (): void {
    $this->seed(DatabaseSeeder::class);

    $emails = [
        'admin@demo.ident.test',
        'automation.bot@demo.ident.test',
        'manager.q1@demo.ident.test',
        'manager.cg@demo.ident.test',
        'manager.hc@demo.ident.test',
        'doctor.q1@demo.ident.test',
        'doctor.cg@demo.ident.test',
        'doctor.hc@demo.ident.test',
        'doctor.float@demo.ident.test',
        'cskh.q1@demo.ident.test',
        'cskh.cg@demo.ident.test',
        'cskh.hc@demo.ident.test',
    ];

    expect(User::query()->whereIn('email', $emails)->count())->toBe(count($emails))
        ->and(User::query()->where('email', 'admin@demo.ident.test')->firstOrFail()->hasRole('Admin'))->toBeTrue()
        ->and(User::query()->where('email', 'automation.bot@demo.ident.test')->firstOrFail()->hasRole('AutomationService'))->toBeTrue()
        ->and(User::query()->where('email', 'manager.cg@demo.ident.test')->firstOrFail()->hasRole('Manager'))->toBeTrue()
        ->and(User::query()->where('email', 'doctor.float@demo.ident.test')->firstOrFail()->hasRole('Doctor'))->toBeTrue()
        ->and(User::query()->where('email', 'doctor.float@demo.ident.test')->firstOrFail()->specialty)->toBe('Phuc hinh')
        ->and(DoctorBranchAssignment::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'doctor.float@demo.ident.test'))
            ->whereHas('branch', fn ($query) => $query->where('code', 'HN-CG'))
            ->where('is_active', true)
            ->exists())->toBeTrue()
        ->and(Hash::check(LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD, (string) User::query()->where('email', 'manager.q1@demo.ident.test')->value('password')))->toBeTrue();
});

it('seeds deterministic crm demo scenarios across appointments finance care labo and zns', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Appointment::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_NO_SHOW)->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_RESCHEDULED)->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_CANCELLED)->exists())->toBeTrue()
        ->and(Appointment::query()->where('note', AppointmentScenarioSeeder::BASE_APPOINTMENT_NOTE)->exists())->toBeTrue()
        ->and(Appointment::query()->where('note', AppointmentScenarioSeeder::FUTURE_GUARD_APPOINTMENT_NOTE)->exists())->toBeTrue()
        ->and(Note::query()->where('care_type', 'no_show_recovery')->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', 'INV-DEMO-Q1-001')->where('status', Invoice::STATUS_PARTIAL)->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', 'INV-DEMO-Q1-002')->where('status', Invoice::STATUS_PAID)->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', 'INV-DEMO-CG-001')->where('status', Invoice::STATUS_OVERDUE)->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', FinanceScenarioSeeder::OVERDUE_INVOICE_NO)->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', FinanceScenarioSeeder::REVERSAL_INVOICE_NO)->exists())->toBeTrue()
        ->and(Invoice::query()->where('invoice_no', FinanceScenarioSeeder::INSTALLMENT_INVOICE_NO)->exists())->toBeTrue()
        ->and(FactoryOrder::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(FactoryOrder::query()->where('order_no', SupplierScenarioSeeder::FACTORY_ORDER_NO)->exists())->toBeTrue()
        ->and(ZnsCampaign::query()->where('code', 'ZNS-DEMO-Q1-RECALL')->where('status', ZnsCampaign::STATUS_COMPLETED)->exists())->toBeTrue()
        ->and(ZnsCampaign::query()->where('code', ZnsAutomationScenarioSeeder::FRESH_CAMPAIGN_CODE)->where('status', ZnsCampaign::STATUS_COMPLETED)->exists())->toBeTrue()
        ->and(ZnsCampaignDelivery::query()->where('idempotency_key', 'ZNS-DEMO-Q1-RECALL-01')->exists())->toBeTrue()
        ->and(ZnsCampaignDelivery::query()->where('idempotency_key', hash('sha256', ZnsAutomationScenarioSeeder::FRESH_SENT_DELIVERY_KEY))->exists())->toBeTrue();
});

it('keeps production master data seeding free from local demo records', function (): void {
    $this->seed(ProductionMasterDataSeeder::class);

    expect(User::query()->where('email', 'admin@demo.ident.test')->exists())->toBeFalse()
        ->and(Branch::query()->where('code', 'HCM-Q1')->exists())->toBeFalse()
        ->and(Material::query()->count())->toBe(0)
        ->and(ClinicSetting::query()->where('key', 'zalo.enabled')->exists())->toBeTrue();
});

function createVietnamTestBranch(string $code, string $name): Branch
{
    return Branch::query()->create([
        'code' => $code,
        'name' => $name,
        'address' => '123 Nguyen Hue, Quan 1, TP.HCM',
        'phone' => '02838229999',
        'active' => true,
    ]);
}
