<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\ProductionMasterDataSeeder;

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
        ->and(User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'doctor.hc@demo.nhakhoaanphuc.test')->exists())->toBeTrue()
        ->and(ClinicSetting::getValue('web_lead.default_branch_code'))->toBe('HCM-Q1');
});

it('keeps production master data seeding free from local demo records', function (): void {
    $this->seed(ProductionMasterDataSeeder::class);

    expect(User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->exists())->toBeFalse()
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
