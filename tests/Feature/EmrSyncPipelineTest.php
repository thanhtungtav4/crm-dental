<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\EmrPatientMap;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\EmrSyncEventPublisher;
use Illuminate\Support\Facades\Http;

it('syncs emr outbox event successfully and persists patient map', function () {
    [$patient] = seedEmrPatientAggregate();

    configureEmrRuntime();

    app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    Http::fake([
        'https://emr.example.test/api/emr/patients/sync' => Http::response([
            'external_patient_id' => 'EMR-PAT-0001',
            'message' => 'ok',
        ], 200),
    ]);

    $this->artisan('emr:sync-events')
        ->assertSuccessful();

    $event = EmrSyncEvent::query()->first();
    $map = EmrPatientMap::query()->where('patient_id', $patient->id)->first();
    $log = EmrSyncLog::query()->where('emr_sync_event_id', $event?->id)->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EmrSyncEvent::STATUS_SYNCED)
        ->and((int) $event?->attempts)->toBe(1)
        ->and($event?->processed_at)->not->toBeNull()
        ->and($map)->not->toBeNull()
        ->and($map?->emr_patient_id)->toBe('EMR-PAT-0001')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(EmrSyncEvent::STATUS_SYNCED);
});

it('moves emr outbox event to dead letter after max attempts', function () {
    [$patient] = seedEmrPatientAggregate();

    configureEmrRuntime();

    $event = app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    expect($event)->not->toBeNull();

    $event?->update([
        'max_attempts' => 1,
    ]);

    Http::fake([
        'https://emr.example.test/api/emr/patients/sync' => Http::response([
            'message' => 'remote error',
        ], 500),
    ]);

    $this->artisan('emr:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();
    $log = EmrSyncLog::query()->where('emr_sync_event_id', $event?->id)->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EmrSyncEvent::STATUS_DEAD)
        ->and((int) $event?->attempts)->toBe(1)
        ->and($event?->last_error)->toContain('remote error')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(EmrSyncEvent::STATUS_FAILED);
});

/**
 * @return array{0: Patient, 1: User}
 */
function seedEmrPatientAggregate(): array
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
        'primary_doctor_id' => $doctor->id,
        'owner_staff_id' => $doctor->id,
    ]);

    PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'allergies' => ['Lidocaine'],
        'chronic_diseases' => ['Tiểu đường'],
        'updated_by' => $doctor->id,
    ]);

    $treatmentPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $treatmentPlan->id,
        'name' => 'Cấy Implant',
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $prescription = Prescription::factory()
        ->forPatient($patient)
        ->byDoctor($doctor)
        ->create();

    PrescriptionItem::factory()
        ->forPrescription($prescription)
        ->antibiotic()
        ->create();

    return [$patient->fresh(), $doctor];
}

function configureEmrRuntime(): void
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
