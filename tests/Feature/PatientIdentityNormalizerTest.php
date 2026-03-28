<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientIdentity;
use App\Models\Patient;
use App\Models\User;
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

it('does not open mpi cross-branch cases for duplicates inside the same branch', function (): void {
    $branch = Branch::factory()->create();

    $patientA = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0901444555',
    ]);

    $patientB = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'phone' => '0901444555',
    ]);

    $service = app(MasterPatientIndexService::class);

    $service->syncForPatient($patientA, true);
    $service->syncForPatient($patientB, true);

    expect($service->hasCrossBranchDuplicate($patientA->fresh()))->toBeFalse()
        ->and($service->hasCrossBranchDuplicate($patientB->fresh()))->toBeFalse()
        ->and(MasterPatientDuplicate::query()->where('status', MasterPatientDuplicate::STATUS_OPEN)->count())->toBe(0);
});

it('auto-ignores open mpi cases through the workflow contract when tracked identities no longer match', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $patientA = Patient::factory()->create([
        'first_branch_id' => $branchA->id,
        'phone' => '0901888999',
    ]);

    $patientB = Patient::factory()->create([
        'first_branch_id' => $branchB->id,
        'phone' => '0901888999',
    ]);

    $service = app(MasterPatientIndexService::class);

    $service->syncForPatient($patientA, true);
    $service->syncForPatient($patientB, true);

    $duplicateCase = MasterPatientDuplicate::query()
        ->where('status', MasterPatientDuplicate::STATUS_OPEN)
        ->firstOrFail();

    $patientB->forceFill([
        'phone' => '0901777666',
    ])->save();

    $service->syncForPatient($patientB->fresh(), true);

    expect($duplicateCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_IGNORED)
        ->and((string) $duplicateCase->fresh()->review_note)->toContain('định danh không còn khớp')
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_DUPLICATE)
            ->where('entity_id', $duplicateCase->id)
            ->where('action', AuditLog::ACTION_RESOLVE)
            ->where('metadata->trigger', 'identity_hash_pruned')
            ->where('metadata->status_to', MasterPatientDuplicate::STATUS_IGNORED)
            ->exists())->toBeTrue();
});
