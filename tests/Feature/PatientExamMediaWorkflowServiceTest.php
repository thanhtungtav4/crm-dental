<?php

use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientExamMediaWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('creates a clinical media asset and original version for a stored indication image', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('patients/1/indications/panorama/example.png', 'fake-image');

    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-06',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-04-06',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $asset = app(PatientExamMediaWorkflowService::class)->createAsset(
        patient: $patient,
        clinicalNote: $note,
        session: $session,
        indicationType: 'panorama',
        storagePath: 'patients/1/indications/panorama/example.png',
        actorId: $doctor->id,
    );

    expect($asset)->not->toBeNull()
        ->and($asset?->storage_path)->toBe('patients/1/indications/panorama/example.png')
        ->and($asset?->modality)->toBe(ClinicalMediaAsset::MODALITY_XRAY)
        ->and(data_get($asset?->meta, 'indication_type'))->toBe('panorama');

    $version = ClinicalMediaVersion::query()
        ->where('clinical_media_asset_id', $asset?->id)
        ->where('is_original', true)
        ->first();

    expect($version)->not->toBeNull()
        ->and((string) $version?->storage_path)->toBe('patients/1/indications/panorama/example.png');
});

it('archives linked clinical media assets and removes the stored file when an indication image is deleted', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('patients/1/indications/ext/example.png', 'fake-image');

    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-07',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $asset = ClinicalMediaAsset::factory()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'branch_id' => $patient->first_branch_id,
        'captured_by' => $doctor->id,
        'storage_disk' => 'public',
        'storage_path' => 'patients/1/indications/ext/example.png',
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
        'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
    ]);

    app(PatientExamMediaWorkflowService::class)->removeAsset(
        patient: $patient,
        session: $session,
        storagePath: 'patients/1/indications/ext/example.png',
    );

    expect($asset->fresh()?->deleted_at)->not->toBeNull()
        ->and($asset->fresh()?->status)->toBe(ClinicalMediaAsset::STATUS_ARCHIVED);

    Storage::disk('public')->assertMissing('patients/1/indications/ext/example.png');
});

it('stores uploads through the workflow service and persists the session clinical note when needed', function (): void {
    Storage::fake('public');

    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-08',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $result = app(PatientExamMediaWorkflowService::class)->storeUploads(
        patient: $patient,
        session: $session,
        clinicalNote: null,
        payload: [
            'examining_doctor_id' => $doctor->id,
            'treating_doctor_id' => $doctor->id,
            'general_exam_notes' => 'Upload first indication image',
            'treatment_plan_note' => null,
            'indications' => ['panorama'],
            'indication_images' => ['panorama' => []],
            'tooth_diagnosis_data' => [],
            'other_diagnosis' => null,
            'updated_by' => $doctor->id,
        ],
        indicationType: 'panorama',
        uploads: [
            UploadedFile::fake()->image('panorama-first.png'),
        ],
        actor: $doctor,
        actorId: $doctor->id,
    );

    $clinicalNote = $result['clinicalNote'] ?? null;
    $storedPath = $result['paths'][0] ?? null;

    expect($clinicalNote)->toBeInstanceOf(ClinicalNote::class)
        ->and($clinicalNote?->exists)->toBeTrue()
        ->and($clinicalNote?->exam_session_id)->toBe($session->id)
        ->and($storedPath)->toBeString()
        ->and($storedPath)->not->toBe('');

    Storage::disk('public')->assertExists((string) $storedPath);

    $asset = ClinicalMediaAsset::query()
        ->where('patient_id', $patient->id)
        ->where('exam_session_id', $session->id)
        ->where('storage_path', (string) $storedPath)
        ->latest('id')
        ->first();

    expect($asset)->not->toBeNull()
        ->and((string) data_get($asset?->meta, 'indication_type'))->toBe('panorama');

    $version = ClinicalMediaVersion::query()
        ->where('clinical_media_asset_id', $asset?->id)
        ->where('is_original', true)
        ->first();

    expect($version)->not->toBeNull()
        ->and((string) $version?->storage_path)->toBe((string) $storedPath);
});
