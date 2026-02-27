<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientMerge;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;

it('merges duplicate patients into canonical record and keeps mapping history', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
        'phone' => '0900000001',
        'email' => null,
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
        'phone' => '0900000002',
        'email' => 'merged@example.test',
    ]);

    $canonicalPatient = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'full_name' => 'Nguyen Canonical',
        'email' => null,
        'phone' => '0900000001',
        'status' => 'active',
    ]);

    $mergedPatient = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'full_name' => 'Nguyen Merged',
        'email' => 'merged@example.test',
        'phone' => '0900000001',
        'status' => 'active',
    ]);

    $appointment = Appointment::factory()->create([
        'patient_id' => $mergedPatient->id,
        'branch_id' => $branchB->id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $note = Note::query()->create([
        'patient_id' => $mergedPatient->id,
        'user_id' => $manager->id,
        'type' => 'general',
        'content' => 'Ghi chú hồ sơ merge.',
    ]);

    $duplicateCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0900000001'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $mergedPatient->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0900000001',
            'matched_patient_ids' => [$canonicalPatient->id, $mergedPatient->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'confidence_score' => 95,
        ]
    );

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonicalPatient->id,
        'merged_patient_id' => $mergedPatient->id,
        '--duplicate_case_id' => $duplicateCase->id,
        '--reason' => 'Duplicate cross-branch',
    ])->assertSuccessful();

    $merge = MasterPatientMerge::query()->latest('id')->first();

    expect($merge)->not->toBeNull()
        ->and($merge->canonical_patient_id)->toBe($canonicalPatient->id)
        ->and($merge->merged_patient_id)->toBe($mergedPatient->id)
        ->and($merge->status)->toBe(MasterPatientMerge::STATUS_APPLIED);

    expect($appointment->fresh()?->patient_id)->toBe($canonicalPatient->id)
        ->and($note->fresh()?->patient_id)->toBe($canonicalPatient->id);

    expect($canonicalPatient->fresh()?->email)->toBe('merged@example.test');

    expect($mergedPatient->fresh()?->status)->toBe('inactive')
        ->and((string) $mergedPatient->fresh()?->note)->toContain('[MERGED_TO:');

    expect($duplicateCase->fresh()?->status)->toBe(MasterPatientDuplicate::STATUS_RESOLVED)
        ->and($duplicateCase->fresh()?->patient_id)->toBe($canonicalPatient->id);

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_MERGE)
        ->where('entity_id', $merge->id)
        ->where('action', AuditLog::ACTION_MERGE)
        ->exists())->toBeTrue();
});

it('rolls back patient merge and restores previous patient references', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
        'phone' => '0900000011',
        'email' => null,
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
        'phone' => '0900000012',
        'email' => 'rollback@example.test',
    ]);

    $canonicalPatient = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'email' => null,
        'phone' => '0900000011',
        'status' => 'active',
    ]);

    $mergedPatient = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'email' => 'rollback@example.test',
        'phone' => '0900000011',
        'status' => 'active',
    ]);

    $appointment = Appointment::factory()->create([
        'patient_id' => $mergedPatient->id,
        'branch_id' => $branchB->id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $duplicateCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0900000011'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $mergedPatient->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0900000011',
            'matched_patient_ids' => [$canonicalPatient->id, $mergedPatient->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'confidence_score' => 95,
        ]
    );

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonicalPatient->id,
        'merged_patient_id' => $mergedPatient->id,
        '--duplicate_case_id' => $duplicateCase->id,
    ])->assertSuccessful();

    $merge = MasterPatientMerge::query()->latest('id')->firstOrFail();

    $this->artisan('mpi:merge-rollback', [
        'merge_id' => $merge->id,
        '--note' => 'Test rollback',
    ])->assertSuccessful();

    expect($appointment->fresh()?->patient_id)->toBe($mergedPatient->id)
        ->and($duplicateCase->fresh()?->status)->toBe(MasterPatientDuplicate::STATUS_OPEN);

    expect($canonicalPatient->fresh()?->email)->toBeNull()
        ->and($mergedPatient->fresh()?->status)->toBe('active');

    expect($merge->fresh()?->status)->toBe(MasterPatientMerge::STATUS_ROLLED_BACK)
        ->and($merge->fresh()?->rolled_back_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_MERGE)
        ->where('entity_id', $merge->id)
        ->where('action', AuditLog::ACTION_ROLLBACK)
        ->exists())->toBeTrue();

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonicalPatient->id,
        'merged_patient_id' => $mergedPatient->id,
        '--reason' => 'Merge again after rollback',
    ])->assertSuccessful();

    expect(MasterPatientMerge::query()
        ->where('canonical_patient_id', $canonicalPatient->id)
        ->where('merged_patient_id', $mergedPatient->id)
        ->where('status', MasterPatientMerge::STATUS_APPLIED)
        ->exists())->toBeTrue();
});
