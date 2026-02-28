<?php

use App\Livewire\PatientExamForm;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores uploaded indication images per selected indication type', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('Doctor');
    $patient = Patient::factory()->create();

    $this->actingAs($user);

    $session = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => $patient->first_branch_id,
        'date' => now()->toDateString(),
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->call('setActiveSession', $session->id)
        ->call('toggleIndication', 'panorama')
        ->set('tempUploads.panorama', [
            UploadedFile::fake()->image('panorama-result.png'),
        ]);

    $session->refresh();

    $images = (array) data_get($session->indication_images, 'panorama', []);
    $firstImagePath = $images[0] ?? null;

    expect($session->indications)->toContain('panorama')
        ->and($images)->toHaveCount(1)
        ->and($firstImagePath)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $firstImagePath);
});
