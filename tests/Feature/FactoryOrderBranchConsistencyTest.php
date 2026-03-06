<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FactoryOrderAuthorizer;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('rejects factory orders whose branch does not match the patient branch', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $admin = User::factory()->create(['branch_id' => $branchA->id]);
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branchA->id,
    ]);
    $supplier = Supplier::query()->create([
        'name' => 'Labo A',
        'code' => 'LABOA',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $this->actingAs($admin);

    expect(fn () => app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($admin, [
        'patient_id' => $patient->id,
        'branch_id' => $branchB->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]))->toThrow(ValidationException::class, 'Chi nhánh của lệnh labo phải trùng với chi nhánh gốc của bệnh nhân.');
});

it('rejects doctors outside the selected branch and allows assigned doctors', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create(['branch_id' => $branchB->id]);
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branchA->id,
    ]);
    $supplier = Supplier::query()->create([
        'name' => 'Labo B',
        'code' => 'LABOB',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($manager, [
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'supplier_id' => $supplier->id,
        'doctor_id' => $doctor->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]))->toThrow(ValidationException::class, 'Bác sĩ được chọn không thuộc phạm vi chi nhánh');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->toDateString(),
        'created_by' => $manager->id,
    ]);

    $data = app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($manager, [
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'supplier_id' => $supplier->id,
        'doctor_id' => $doctor->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    expect($data['doctor_id'])->toBe($doctor->id)
        ->and($data['branch_id'])->toBe($branchA->id);
});

it('wires factory order form and pages through the branch consistency authorizer', function (): void {
    $form = File::get(app_path('Filament/Resources/FactoryOrders/Schemas/FactoryOrderForm.php'));
    $createPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/CreateFactoryOrder.php'));
    $editPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/EditFactoryOrder.php'));
    $model = File::get(app_path('Models/FactoryOrder.php'));

    expect($form)
        ->toContain('FactoryOrderAuthorizer')
        ->toContain('assignableDoctorOptions')
        ->toContain("Select::make('supplier_id')")
        ->toContain('Chi hien thi bac si thuoc chi nhanh dang chon.');

    expect($createPage)
        ->toContain('FactoryOrderAuthorizer::class');

    expect($editPage)
        ->toContain('FactoryOrderAuthorizer::class');

    expect($model)
        ->toContain('Chi nhánh của lệnh labo phải trùng với chi nhánh gốc của bệnh nhân.')
        ->toContain('ensureDoctorCanWorkAtBranch');
});
