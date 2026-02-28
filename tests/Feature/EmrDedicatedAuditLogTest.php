<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\EmrSyncEventPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('records emr dedicated logs for publish and sync event lifecycle', function () {
    [$patient] = seedDedicatedEmrAuditPatient();

    configureDedicatedEmrRuntime();

    app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    Http::fake([
        'https://emr.example.test/api/emr/patients/sync' => Http::response([
            'external_patient_id' => 'EMR-PAT-9001',
            'message' => 'ok',
        ], 200),
    ]);

    $this->artisan('emr:sync-events')
        ->assertSuccessful();

    $patientLogs = EmrAuditLog::query()
        ->forPatient((int) $patient->id)
        ->where('entity_type', EmrAuditLog::ENTITY_SYNC_EVENT)
        ->orderBy('id')
        ->get();

    expect($patientLogs)->toHaveCount(2)
        ->and((string) $patientLogs[0]->action)->toBe(EmrAuditLog::ACTION_PUBLISH)
        ->and((string) $patientLogs[1]->action)->toBe(EmrAuditLog::ACTION_SYNC)
        ->and((string) data_get($patientLogs[1]->context, 'status'))->toBe('synced');
});

it('records clinical order and result logs queryable by encounter', function () {
    $branch = Branch::factory()->create();
    $doctor = makeDedicatedAuditDoctorForBranch($branch);
    $patient = makeDedicatedAuditPatientForBranch($branch);

    $this->actingAs($doctor);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => '2026-03-10 08:00:00',
        'in_chair_at' => '2026-03-10 08:10:00',
        'planned_duration_minutes' => 45,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-10',
        'examination_note' => 'Đau vùng răng 16',
    ]);

    $order = ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
    ]);

    $order->markInProgress();
    $order->markCompleted();

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
        'payload' => ['modality' => 'panorama'],
    ]);

    $result->finalize(verifiedBy: $doctor->id, interpretation: 'Không phát hiện bất thường lớn.');

    $encounterLogs = EmrAuditLog::query()
        ->forEncounter((int) $encounter->id)
        ->orderBy('id')
        ->get();

    expect($encounterLogs->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_ORDER))->not->toBeEmpty()
        ->and($encounterLogs->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_RESULT))->not->toBeEmpty()
        ->and($encounterLogs->where('action', EmrAuditLog::ACTION_COMPLETE))->not->toBeEmpty()
        ->and($encounterLogs->where('action', EmrAuditLog::ACTION_FINALIZE))->not->toBeEmpty();
});

it('keeps emr audit logs immutable against update and delete', function () {
    $log = EmrAuditLog::factory()->create([
        'entity_type' => EmrAuditLog::ENTITY_SYNC_EVENT,
        'entity_id' => 1234,
        'action' => EmrAuditLog::ACTION_SYNC,
    ]);

    expect(fn () => $log->update(['action' => EmrAuditLog::ACTION_FAIL]))
        ->toThrow(ValidationException::class);

    expect(fn () => $log->delete())
        ->toThrow(ValidationException::class);

    expect($log->fresh()?->action)->toBe(EmrAuditLog::ACTION_SYNC);
});

/**
 * @return array{0: Patient, 1: User}
 */
function seedDedicatedEmrAuditPatient(): array
{
    $branch = Branch::factory()->create();
    $doctor = makeDedicatedAuditDoctorForBranch($branch);
    $patient = makeDedicatedAuditPatientForBranch($branch);

    return [$patient, $doctor];
}

function makeDedicatedAuditDoctorForBranch(Branch $branch): User
{
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->updateOrCreate(
        [
            'user_id' => $doctor->id,
            'branch_id' => $branch->id,
        ],
        [
            'is_active' => true,
            'is_primary' => true,
        ],
    );

    return $doctor;
}

function makeDedicatedAuditPatientForBranch(Branch $branch): Patient
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

function configureDedicatedEmrRuntime(): void
{
    ClinicSetting::setValue('emr.enabled', true, [
        'group' => 'emr',
        'label' => 'Bật EMR',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.provider', 'external', [
        'group' => 'emr',
        'label' => 'Nhà cung cấp EMR',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.base_url', 'https://emr.example.test', [
        'group' => 'emr',
        'label' => 'EMR Base URL',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.api_key', 'test-api-key', [
        'group' => 'emr',
        'label' => 'EMR API Key',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.clinic_code', 'CLINIC-HQ', [
        'group' => 'emr',
        'label' => 'Mã cơ sở',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);
}
