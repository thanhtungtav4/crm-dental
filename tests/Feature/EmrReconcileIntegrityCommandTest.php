<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Support\Facades\DB;

it('passes emr reconcile command when data is consistent', function () {
    $context = seedEmrReconcileContext();

    $this->artisan('emr:reconcile-integrity', [
        '--strict' => true,
    ])->assertSuccessful();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->command', 'emr:reconcile-integrity')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) data_get($audit?->metadata, 'total_issues'))->toBe(0);
});

it('fails in strict mode when revision version mismatch exists', function () {
    $context = seedEmrReconcileContext();
    $note = $context['note'];

    DB::table('clinical_notes')
        ->where('id', (int) $note->id)
        ->update(['lock_version' => 99]);

    $this->artisan('emr:reconcile-integrity', [
        '--strict' => true,
    ])->assertFailed();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->where('metadata->command', 'emr:reconcile-integrity')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) data_get($audit?->metadata, 'findings.note_revision_version_mismatch'))->toBeGreaterThan(0);
});

/**
 * @return array{note: ClinicalNote}
 */
function seedEmrReconcileContext(): array
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
        'scheduled_at' => '2026-03-14 09:00:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-14',
        'general_exam_notes' => 'Khám ổn định',
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    return [
        'note' => $note,
    ];
}
