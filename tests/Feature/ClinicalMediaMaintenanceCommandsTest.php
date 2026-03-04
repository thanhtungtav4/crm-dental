<?php

use App\Models\Branch;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('backfills legacy photos and indication images into clinical media assets idempotently', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaMaintenancePatient();
    $patient = $context['patient'];
    $doctor = $context['doctor'];

    $photoPath = 'patients/'.$patient->id.'/legacy/photo-a.jpg';
    $notePath = 'patients/'.$patient->id.'/legacy/panorama-a.jpg';
    Storage::disk('public')->put($photoPath, 'legacy-photo');
    Storage::disk('public')->put($notePath, 'legacy-note');

    PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_EXTERNAL,
        'date' => now()->toDateString(),
        'title' => 'Legacy extra oral',
        'content' => [$photoPath],
    ]);

    ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => now()->toDateString(),
        'indications' => ['panorama'],
        'indication_images' => [
            'panorama' => [$notePath],
        ],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $this->artisan('emr:backfill-clinical-media', ['--strict' => true])
        ->assertSuccessful();

    $firstCount = ClinicalMediaAsset::query()
        ->where('patient_id', $patient->id)
        ->count();

    $this->artisan('emr:backfill-clinical-media', ['--strict' => true])
        ->assertSuccessful();

    $secondCount = ClinicalMediaAsset::query()
        ->where('patient_id', $patient->id)
        ->count();

    $versionCount = ClinicalMediaVersion::query()
        ->whereIn('clinical_media_asset_id', ClinicalMediaAsset::query()
            ->where('patient_id', $patient->id)
            ->pluck('id')
            ->all())
        ->where('is_original', true)
        ->count();

    expect($firstCount)->toBeGreaterThanOrEqual(2)
        ->and($secondCount)->toBe($firstCount)
        ->and($versionCount)->toBe($secondCount);
});

it('fails reconcile command in strict mode when checksum is missing', function (): void {
    $context = seedClinicalMediaMaintenancePatient();
    $patient = $context['patient'];

    $asset = ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_at' => now(),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/reconcile-missing-checksum.jpg',
        'checksum_sha256' => null,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    ClinicalMediaVersion::query()->create([
        'clinical_media_asset_id' => $asset->id,
        'version_number' => 1,
        'is_original' => true,
        'mime_type' => 'image/jpeg',
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/reconcile-missing-checksum.jpg',
        'checksum_sha256' => null,
    ]);

    $this->artisan('emr:reconcile-clinical-media', ['--strict' => true])
        ->expectsOutputToContain('missing_checksum_asset')
        ->assertFailed();
});

it('prunes only allowed retention classes and skips legal hold assets', function (): void {
    Storage::fake('public');

    $context = seedClinicalMediaMaintenancePatient();
    $patient = $context['patient'];

    $temporaryPath = 'clinical-media/prune/temporary.jpg';
    $temporaryHoldPath = 'clinical-media/prune/temporary-hold.jpg';
    $clinicalLegalPath = 'clinical-media/prune/clinical-legal.jpg';
    Storage::disk('public')->put($temporaryPath, 'tmp');
    Storage::disk('public')->put($temporaryHoldPath, 'tmp-hold');
    Storage::disk('public')->put($clinicalLegalPath, 'clinical-legal');

    $temporary = ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_at' => now()->subDays(10),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'tmp'),
        'storage_disk' => 'public',
        'storage_path' => $temporaryPath,
        'retention_class' => ClinicalMediaAsset::RETENTION_TEMPORARY,
        'legal_hold' => false,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    $temporaryHold = ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_at' => now()->subDays(10),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'tmp-hold'),
        'storage_disk' => 'public',
        'storage_path' => $temporaryHoldPath,
        'retention_class' => ClinicalMediaAsset::RETENTION_TEMPORARY,
        'legal_hold' => true,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    $clinicalLegal = ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_at' => now()->subDays(10),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'clinical-legal'),
        'storage_disk' => 'public',
        'storage_path' => $clinicalLegalPath,
        'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_LEGAL,
        'legal_hold' => false,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    $this->artisan('emr:prune-clinical-media', [
        '--class' => [ClinicalMediaAsset::RETENTION_TEMPORARY],
        '--days' => 1,
        '--strict' => true,
    ])->assertSuccessful();

    expect(ClinicalMediaAsset::query()->withTrashed()->find($temporary->id)?->trashed())->toBeTrue()
        ->and(ClinicalMediaAsset::query()->find($temporaryHold->id))->not->toBeNull()
        ->and(ClinicalMediaAsset::query()->find($clinicalLegal->id))->not->toBeNull();

    Storage::disk('public')->assertMissing($temporaryPath);
    Storage::disk('public')->assertExists($temporaryHoldPath);
    Storage::disk('public')->assertExists($clinicalLegalPath);
});

it('enforces strict dicom readiness only when dicom is enabled', function (): void {
    ClinicSetting::setValue('emr.dicom.enabled', true, [
        'value_type' => 'boolean',
    ]);

    $this->artisan('emr:check-dicom-readiness', ['--strict' => true])
        ->expectsOutputToContain('DICOM_READY: no')
        ->assertFailed();

    ClinicSetting::setValue('emr.dicom.base_url', 'https://dicom.example.test', ['value_type' => 'text']);
    ClinicSetting::setValue('emr.dicom.facility_code', 'HCM-01', ['value_type' => 'text']);
    ClinicSetting::setValue('emr.dicom.auth_token', 'secret-token', ['value_type' => 'text']);
    ClinicSetting::flushRuntimeCache();

    $this->artisan('emr:check-dicom-readiness', ['--strict' => true])
        ->expectsOutputToContain('DICOM_READY: yes')
        ->assertSuccessful();
});

/**
 * @return array{patient: Patient, doctor: User}
 */
function seedClinicalMediaMaintenancePatient(): array
{
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    return [
        'patient' => $patient,
        'doctor' => $doctor,
    ];
}
