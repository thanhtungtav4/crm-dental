<?php

use App\Filament\Resources\PatientWallets\PatientWalletResource;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\User;

it('forbids doctor from viewing patient wallet resource', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 100_000,
    ]);

    $this->actingAs($doctor);

    expect(PatientWalletResource::canViewAny())->toBeFalse();

    $this->get(PatientWalletResource::getUrl('index'))
        ->assertForbidden();
});

it('allows manager to view and edit patient wallets in accessible branch', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 150_000,
    ]);

    $this->actingAs($manager);

    expect(PatientWalletResource::canViewAny())->toBeTrue()
        ->and(PatientWalletResource::canEdit($wallet))->toBeTrue();

    $this->get(PatientWalletResource::getUrl('index'))
        ->assertSuccessful();
});
