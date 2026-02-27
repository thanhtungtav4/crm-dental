<?php

use App\Models\Patient;
use App\Support\PatientCodeGenerator;

it('only previews invalid patient codes when apply option is not provided', function () {
    $invalidPatient = Patient::factory()->create([
        'patient_code' => 'legacy-code',
    ]);

    $validPatient = Patient::factory()->create([
        'patient_code' => PatientCodeGenerator::generate(),
    ]);

    $this->artisan('patients:backfill-codes')
        ->expectsOutputToContain('Tim thay 1 ho so co ma khong dung chuan.')
        ->expectsOutputToContain('Chay lai voi --apply de cap nhat du lieu.')
        ->assertSuccessful();

    expect($invalidPatient->fresh()->patient_code)->toBe('legacy-code');
    expect($validPatient->fresh()->patient_code)->toMatch('/^PAT-\d{8}-[A-Z0-9]{6}$/');
});

it('backfills invalid patient codes when apply option is provided', function () {
    $invalidPatientOne = Patient::factory()->create([
        'patient_code' => 'invalid-one',
    ]);

    $invalidPatientTwo = Patient::factory()->create([
        'patient_code' => null,
    ]);

    $validPatient = Patient::factory()->create([
        'patient_code' => PatientCodeGenerator::generate(),
    ]);

    $this->artisan('patients:backfill-codes', ['--apply' => true])
        ->expectsOutputToContain('Da backfill 2 ma ho so.')
        ->assertSuccessful();

    expect(PatientCodeGenerator::isStandard($invalidPatientOne->fresh()->patient_code))->toBeTrue();
    expect(PatientCodeGenerator::isStandard($invalidPatientTwo->fresh()->patient_code))->toBeTrue();
    expect(PatientCodeGenerator::isStandard($validPatient->fresh()->patient_code))->toBeTrue();
});
