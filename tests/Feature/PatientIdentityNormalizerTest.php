<?php

use App\Models\Customer;
use App\Models\MasterPatientIdentity;
use App\Models\Patient;
use App\Services\MasterPatientIndexService;
use App\Support\PatientIdentityNormalizer;

it('normalizes phone email and cccd consistently across equivalent input formats', function (): void {
    expect(PatientIdentityNormalizer::normalizePhone('+84 901 234 567'))->toBe('0901234567')
        ->and(PatientIdentityNormalizer::normalizePhone('0901 234 567'))->toBe('0901234567')
        ->and(PatientIdentityNormalizer::normalizeEmail('  TEST@Example.com '))->toBe('test@example.com')
        ->and(PatientIdentityNormalizer::normalizeCccd(' 0123 456 789 '))->toBe('0123456789');
});

it('keeps customer and patient hash generation aligned on the shared normalizer contract', function (): void {
    $normalizedPhone = PatientIdentityNormalizer::normalizePhone('+84 901 222 333');
    $normalizedEmail = PatientIdentityNormalizer::normalizeEmail(' Lead@Example.com ');

    expect(Customer::phoneSearchHash('+84 901 222 333'))
        ->toBe(hash('sha256', 'customer-phone|'.$normalizedPhone))
        ->and(Patient::phoneSearchHash('0901222333'))
        ->toBe(hash('sha256', 'patient-phone|'.$normalizedPhone))
        ->and(Customer::emailSearchHash(' Lead@Example.com '))
        ->toBe(hash('sha256', 'customer-email|'.$normalizedEmail))
        ->and(Patient::emailSearchHash('lead@example.com'))
        ->toBe(hash('sha256', 'patient-email|'.$normalizedEmail));
});

it('persists mpi identities using the shared normalized values', function (): void {
    $patient = Patient::factory()->create([
        'phone' => '+84 901 333 444',
        'email' => ' MPI@Example.com ',
        'cccd' => ' 0123 456 789 ',
    ]);

    app(MasterPatientIndexService::class)->syncForPatient($patient);

    $identities = MasterPatientIdentity::query()
        ->where('patient_id', $patient->id)
        ->pluck('identity_value', 'identity_type');

    expect($identities[MasterPatientIdentity::TYPE_PHONE])->toBe('0901333444')
        ->and($identities[MasterPatientIdentity::TYPE_EMAIL])->toBe('mpi@example.com')
        ->and($identities[MasterPatientIdentity::TYPE_CCCD])->toBe('0123456789');
});
