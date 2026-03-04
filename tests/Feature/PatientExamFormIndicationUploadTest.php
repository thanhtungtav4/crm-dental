<?php

use App\Livewire\PatientExamForm;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores uploaded indication images per selected indication type', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('Admin');
    $patient = Patient::factory()->create();

    $this->actingAs($user);

    $examSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => now()->toDateString(),
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $examSession->id,
        'doctor_id' => $user->id,
        'branch_id' => $patient->first_branch_id,
        'date' => now()->toDateString(),
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $note->refresh();

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->call('setActiveSession', (int) $examSession->id)
        ->call('toggleIndication', 'panorama')
        ->set('tempUploads.panorama', [
            UploadedFile::fake()->image('panorama-result.png'),
        ]);

    $note->refresh();

    $images = (array) data_get($note->indication_images, 'panorama', []);
    $firstImagePath = $images[0] ?? null;

    expect($note->indications)->toContain('panorama')
        ->and($images)->toHaveCount(1)
        ->and($firstImagePath)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $firstImagePath);

    $mediaAsset = ClinicalMediaAsset::query()
        ->where('patient_id', $patient->id)
        ->where('exam_session_id', $examSession->id)
        ->where('storage_path', (string) $firstImagePath)
        ->latest('id')
        ->first();

    expect($mediaAsset)->not->toBeNull()
        ->and((string) data_get($mediaAsset?->meta, 'indication_type'))->toBe('panorama');

    $mediaVersion = ClinicalMediaVersion::query()
        ->where('clinical_media_asset_id', $mediaAsset?->id)
        ->where('is_original', true)
        ->first();

    expect($mediaVersion)->not->toBeNull()
        ->and((string) $mediaVersion?->storage_path)->toBe((string) $firstImagePath);
});
