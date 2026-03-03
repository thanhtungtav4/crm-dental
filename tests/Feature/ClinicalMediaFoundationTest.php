<?php

use App\Models\Branch;
use App\Models\ClinicalMediaAccessLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\Customer;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Policies\ClinicalMediaAssetPolicy;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('provisions clinical media foundation schema with required keys', function (): void {
    expect(Schema::hasTable('clinical_media_assets'))->toBeTrue()
        ->and(Schema::hasTable('clinical_media_versions'))->toBeTrue()
        ->and(Schema::hasTable('clinical_media_access_logs'))->toBeTrue();

    expect(Schema::hasColumns('clinical_media_assets', [
        'patient_id',
        'visit_episode_id',
        'exam_session_id',
        'plan_item_id',
        'treatment_session_id',
        'clinical_order_id',
        'clinical_result_id',
        'prescription_id',
        'branch_id',
        'captured_by',
        'consent_id',
        'captured_at',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'retention_class',
        'legal_hold',
    ]))->toBeTrue();

    expect(Schema::hasColumns('clinical_media_versions', [
        'clinical_media_asset_id',
        'version_number',
        'is_original',
        'checksum_sha256',
        'storage_path',
    ]))->toBeTrue();

    expect(Schema::hasColumns('clinical_media_access_logs', [
        'clinical_media_asset_id',
        'clinical_media_version_id',
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'actor_id',
        'action',
        'occurred_at',
    ]))->toBeTrue();

    $assetForeignKeys = Schema::getForeignKeys('clinical_media_assets');
    $versionForeignKeys = Schema::getForeignKeys('clinical_media_versions');
    $accessLogForeignKeys = Schema::getForeignKeys('clinical_media_access_logs');

    expect(hasForeignKey($assetForeignKeys, 'patient_id', 'patients', 'id'))->toBeTrue()
        ->and(hasForeignKey($assetForeignKeys, 'exam_session_id', 'exam_sessions', 'id'))->toBeTrue()
        ->and(hasForeignKey($assetForeignKeys, 'branch_id', 'branches', 'id'))->toBeTrue()
        ->and(hasForeignKey($versionForeignKeys, 'clinical_media_asset_id', 'clinical_media_assets', 'id'))->toBeTrue()
        ->and(hasForeignKey($accessLogForeignKeys, 'clinical_media_asset_id', 'clinical_media_assets', 'id'))->toBeTrue();
});

it('infers patient and branch from exam session and keeps access logs immutable', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $patient = createClinicalMediaPatientForBranch($branch);

    $visitEpisode = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => now(),
        'in_chair_at' => now(),
        'planned_duration_minutes' => 30,
    ]);

    $examSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $visitEpisode->id,
        'branch_id' => $branch->id,
        'doctor_id' => $manager->id,
        'session_date' => now()->toDateString(),
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($manager);

    $asset = ClinicalMediaAsset::query()->create([
        'exam_session_id' => $examSession->id,
        'captured_by' => $manager->id,
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'asset-example'),
        'storage_path' => 'clinical-media/example.jpg',
    ]);

    $version = ClinicalMediaVersion::query()->create([
        'clinical_media_asset_id' => $asset->id,
        'is_original' => true,
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'version-example'),
        'storage_path' => 'clinical-media/versions/example-v1.jpg',
        'created_by' => $manager->id,
    ]);

    $accessLog = ClinicalMediaAccessLog::query()->create([
        'clinical_media_asset_id' => $asset->id,
        'clinical_media_version_id' => $version->id,
        'actor_id' => $manager->id,
        'action' => ClinicalMediaAccessLog::ACTION_VIEW,
        'purpose' => 'Clinical review',
    ]);

    expect($asset->patient_id)->toBe($patient->id)
        ->and($asset->branch_id)->toBe($branch->id)
        ->and($asset->status)->toBe(ClinicalMediaAsset::STATUS_ACTIVE)
        ->and($asset->retention_class)->toBe(ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL)
        ->and($version->version_number)->toBe(1)
        ->and($accessLog->patient_id)->toBe($patient->id)
        ->and($accessLog->branch_id)->toBe($branch->id);

    expect(fn (): bool => $accessLog->update(['purpose' => 'Tamper attempt']))
        ->toThrow(ValidationException::class);

    expect(fn (): ?bool => $accessLog->delete())
        ->toThrow(ValidationException::class);
});

it('enforces branch aware policy checks for clinical media assets', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $managerB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $managerB->assignRole('Manager');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patientA = createClinicalMediaPatientForBranch($branchA);

    $asset = ClinicalMediaAsset::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'storage_path' => 'clinical-media/policy-check.jpg',
        'checksum_sha256' => hash('sha256', 'policy-check'),
    ]);

    $policy = app(ClinicalMediaAssetPolicy::class);

    expect($policy->view($managerA, $asset))->toBeTrue()
        ->and($policy->view($managerB, $asset))->toBeFalse()
        ->and($policy->view($admin, $asset))->toBeTrue()
        ->and($policy->create($managerA))->toBeTrue();
});

/**
 * @param  array<int, array<string, mixed>>  $foreignKeys
 */
function hasForeignKey(array $foreignKeys, string $column, string $foreignTable, string $foreignColumn): bool
{
    return collect($foreignKeys)->contains(function (array $foreignKey) use ($column, $foreignTable, $foreignColumn): bool {
        return (array) ($foreignKey['columns'] ?? []) === [$column]
            && (string) ($foreignKey['foreign_table'] ?? '') === $foreignTable
            && (array) ($foreignKey['foreign_columns'] ?? []) === [$foreignColumn];
    });
}

function createClinicalMediaPatientForBranch(Branch $branch): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
}
