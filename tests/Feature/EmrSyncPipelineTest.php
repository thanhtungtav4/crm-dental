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
use Illuminate\Support\Facades\Concurrency;
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

it('keeps deterministic idempotency key for repeated emr publish attempts and requeues after synced', function (): void {
    [$patient] = seedEmrPatientAggregate();

    configureEmrRuntime();

    $patientId = (int) $patient->id;
    $tasks = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static fn (): ?int => app(EmrSyncEventPublisher::class)
            ->publishForPatientId($patientId, 'manual.sync')?->id;
    }

    $resultIds = Concurrency::driver('sync')->run($tasks);

    $event = EmrSyncEvent::query()
        ->where('patient_id', $patientId)
        ->where('event_type', 'manual.sync')
        ->first();

    expect($event)->not->toBeNull()
        ->and(collect($resultIds)->filter()->unique()->count())->toBe(1)
        ->and(EmrSyncEvent::query()->count())->toBe(1)
        ->and($event?->status)->toBe(EmrSyncEvent::STATUS_PENDING);

    $event?->markSynced('EMR-PAT-0001', 200);

    $replayedEvent = app(EmrSyncEventPublisher::class)
        ->publishForPatientId($patientId, 'manual.sync');

    expect($replayedEvent)->not->toBeNull()
        ->and((int) $replayedEvent?->id)->toBe((int) $event?->id)
        ->and($replayedEvent?->status)->toBe(EmrSyncEvent::STATUS_PENDING)
        ->and((int) $replayedEvent?->attempts)->toBe(0);
});

it('reclaims stale processing emr events and retries them successfully', function (): void {
    [$patient] = seedEmrPatientAggregate();

    configureEmrRuntime();

    $event = app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    expect($event)->not->toBeNull();

    $event?->forceFill([
        'status' => EmrSyncEvent::STATUS_PROCESSING,
        'attempts' => 1,
        'locked_at' => now()->subMinutes(30),
        'next_retry_at' => now()->addMinutes(20),
        'last_error' => 'simulated worker crash',
    ])->save();

    Http::fake([
        'https://emr.example.test/api/emr/patients/sync' => Http::response([
            'external_patient_id' => 'EMR-PAT-RECOVER-0001',
            'message' => 'ok',
        ], 200),
    ]);

    $this->artisan('emr:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EmrSyncEvent::STATUS_SYNCED)
        ->and((int) $event?->attempts)->toBe(2)
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toBe('');
});

it('moves stale processing emr events to dead when max attempts already reached', function (): void {
    [$patient] = seedEmrPatientAggregate();

    configureEmrRuntime();

    $event = app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    expect($event)->not->toBeNull();

    $event?->forceFill([
        'status' => EmrSyncEvent::STATUS_PROCESSING,
        'attempts' => 2,
        'max_attempts' => 2,
        'locked_at' => now()->subMinutes(30),
        'next_retry_at' => now()->addMinutes(10),
        'last_error' => 'simulated worker crash',
    ])->save();

    Http::preventStrayRequests();

    $this->artisan('emr:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EmrSyncEvent::STATUS_DEAD)
        ->and((int) $event?->attempts)->toBe(2)
        ->and($event?->next_retry_at)->toBeNull()
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toContain('max attempts');

    Http::assertNothingSent();
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
