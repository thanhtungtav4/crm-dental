<?php

use App\Livewire\PatientExamForm;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Livewire\Livewire;

use function Pest\Laravel\seed;

it('renders the upgraded patient exam form controls for session creation and autosave feedback', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('patient_code', 'PAT-QA-TRT-001')
        ->firstOrFail();

    Livewire::actingAs($doctor)
        ->test(PatientExamForm::class, ['patient' => $patient])
        ->call('createSession')
        ->assertSee('Đang mở: '.now()->format('d/m/Y'))
        ->assertSee('Ngày khám mới')
        ->assertSee('Thêm phiếu khám')
        ->assertSee('Đang đồng bộ dữ liệu...')
        ->assertSee('Bác sĩ điều trị')
        ->assertSee('Clinical evidence completeness')
        ->assertSee('Chọn nhiều');
});

it('does not persist a blank clinical note when opening a new exam session', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('patient_code', 'PAT-QA-TRT-001')
        ->firstOrFail();

    expect($patient->clinicalNotes()->count())->toBe(0);

    Livewire::actingAs($doctor)
        ->test(PatientExamForm::class, ['patient' => $patient])
        ->call('createSession')
        ->assertSet('examining_doctor_id', $doctor->id)
        ->assertSet('treating_doctor_id', $doctor->id);

    expect($patient->fresh()->clinicalNotes()->count())->toBe(0)
        ->and($patient->fresh()->examSessions()->count())->toBe(1);
});

it('persists the clinical note only after the doctor starts entering exam data', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('patient_code', 'PAT-QA-TRT-001')
        ->firstOrFail();

    Livewire::actingAs($doctor)
        ->test(PatientExamForm::class, ['patient' => $patient])
        ->call('createSession')
        ->set('general_exam_notes', 'Khám kiểm tra ban đầu');

    $note = $patient->fresh()->clinicalNotes()->first();

    expect($note)->not->toBeNull()
        ->and($note?->general_exam_notes)->toBe('Khám kiểm tra ban đầu')
        ->and($note?->examining_doctor_id)->toBe($doctor->id)
        ->and($note?->treating_doctor_id)->toBe($doctor->id);
});
