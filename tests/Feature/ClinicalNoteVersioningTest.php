<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalNoteRevision;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\ClinicalNoteVersioningService;
use Illuminate\Validation\ValidationException;

it('creates initial revision when clinical note is created', function () {
    [$patient, $doctor, $encounter] = seedClinicalVersioningContext();

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $encounter->branch_id,
        'date' => '2026-03-12',
        'general_exam_notes' => 'Khám ban đầu.',
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $revision = ClinicalNoteRevision::query()
        ->where('clinical_note_id', $note->id)
        ->where('version', 1)
        ->first();

    expect((int) $note->lock_version)->toBe(1)
        ->and($revision)->not->toBeNull()
        ->and((string) $revision?->operation)->toBe(ClinicalNoteRevision::OPERATION_CREATE);
});

it('increments lock version and writes revision on optimistic update', function () {
    [$patient, $doctor, $encounter] = seedClinicalVersioningContext();

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $encounter->branch_id,
        'date' => '2026-03-12',
        'general_exam_notes' => 'Khám ban đầu.',
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $updated = app(ClinicalNoteVersioningService::class)->updateWithOptimisticLock(
        clinicalNote: $note,
        attributes: [
            'general_exam_notes' => 'Khám cập nhật lần 2.',
            'other_diagnosis' => 'Viêm tủy răng 26.',
        ],
        expectedVersion: 1,
        actorId: $doctor->id,
        operation: ClinicalNoteRevision::OPERATION_AMEND,
        reason: 'manual_amend',
    );

    $revision = ClinicalNoteRevision::query()
        ->where('clinical_note_id', $note->id)
        ->where('version', 2)
        ->first();

    expect((int) $updated->lock_version)->toBe(2)
        ->and($revision)->not->toBeNull()
        ->and((string) $revision?->operation)->toBe(ClinicalNoteRevision::OPERATION_AMEND)
        ->and((array) $revision?->changed_fields)->toContain('general_exam_notes')
        ->and((string) data_get($revision?->current_payload, 'general_exam_notes'))->toBe('Khám cập nhật lần 2.');
});

it('rejects stale clinical note version update', function () {
    [$patient, $doctor, $encounter] = seedClinicalVersioningContext();

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $encounter->branch_id,
        'date' => '2026-03-12',
        'general_exam_notes' => 'Khám ban đầu.',
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    app(ClinicalNoteVersioningService::class)->updateWithOptimisticLock(
        clinicalNote: $note,
        attributes: ['general_exam_notes' => 'Phiên bản mới'],
        expectedVersion: 1,
        actorId: $doctor->id,
    );

    expect(fn () => app(ClinicalNoteVersioningService::class)->updateWithOptimisticLock(
        clinicalNote: $note,
        attributes: ['general_exam_notes' => 'Phiên bản cũ'],
        expectedVersion: 1,
        actorId: $doctor->id,
    ))->toThrow(ValidationException::class);

    $note->refresh();

    expect((int) $note->lock_version)->toBe(2)
        ->and((string) $note->general_exam_notes)->toBe('Phiên bản mới');
});

/**
 * @return array{0: Patient, 1: User, 2: VisitEpisode}
 */
function seedClinicalVersioningContext(): array
{
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
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

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-12 09:00:00',
        'planned_duration_minutes' => 30,
    ]);

    return [$patient, $doctor, $encounter];
}
