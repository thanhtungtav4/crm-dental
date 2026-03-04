<?php

use App\Models\Branch;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Customer;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\EmrPatientPayloadBuilder;

it('includes clinical media dossier and linkage in EMR payload', function (): void {
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

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => now()->subHours(3),
        'in_chair_at' => now()->subHours(2),
        'planned_duration_minutes' => 45,
    ]);

    $examSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'session_date' => now()->toDateString(),
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $order = ClinicalOrder::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'exam_session_id' => $examSession->id,
        'branch_id' => $branch->id,
        'ordered_by' => $doctor->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
    ]);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
    ]);

    $prescription = Prescription::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'exam_session_id' => $examSession->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'treatment_date' => now()->toDateString(),
        'prescription_name' => 'Paracetamol',
    ]);

    $encounterAsset = createPayloadClinicalMediaAsset(
        patient: $patient,
        branchId: $branch->id,
        linkages: ['visit_episode_id' => $encounter->id],
        suffix: 'encounter',
    );
    $sessionAsset = createPayloadClinicalMediaAsset(
        patient: $patient,
        branchId: $branch->id,
        linkages: ['exam_session_id' => $examSession->id],
        suffix: 'session',
    );
    $orderAsset = createPayloadClinicalMediaAsset(
        patient: $patient,
        branchId: $branch->id,
        linkages: ['clinical_order_id' => $order->id],
        suffix: 'order',
    );
    $resultAsset = createPayloadClinicalMediaAsset(
        patient: $patient,
        branchId: $branch->id,
        linkages: ['clinical_result_id' => $result->id],
        suffix: 'result',
    );
    $prescriptionAsset = createPayloadClinicalMediaAsset(
        patient: $patient,
        branchId: $branch->id,
        linkages: ['prescription_id' => $prescription->id],
        suffix: 'prescription',
    );

    $payload = app(EmrPatientPayloadBuilder::class)->build($patient->fresh());

    expect(data_get($payload, 'media.records'))->toBeArray()
        ->and(data_get($payload, 'media.summary.total_assets'))->toBeGreaterThanOrEqual(5)
        ->and(data_get($payload, 'encounter.records.0.media_ids'))->toContain($encounterAsset->id)
        ->and(data_get($payload, 'exam_session.records.0.media_ids'))->toContain($sessionAsset->id)
        ->and(data_get($payload, 'order.records.0.media_ids'))->toContain($orderAsset->id)
        ->and(data_get($payload, 'result.records.0.media_ids'))->toContain($resultAsset->id)
        ->and(data_get($payload, 'prescription.records.0.media_ids'))->toContain($prescriptionAsset->id)
        ->and(data_get($payload, 'meta.media_asset_ids'))->toContain($encounterAsset->id, $sessionAsset->id, $orderAsset->id, $resultAsset->id, $prescriptionAsset->id);
});

/**
 * @param  array<string, int|null>  $linkages
 */
function createPayloadClinicalMediaAsset(Patient $patient, int $branchId, array $linkages, string $suffix): ClinicalMediaAsset
{
    $asset = ClinicalMediaAsset::query()->create(array_merge([
        'patient_id' => $patient->id,
        'branch_id' => $branchId,
        'captured_at' => now(),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'payload-'.$suffix),
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/payload-'.$suffix.'.jpg',
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ], $linkages));

    ClinicalMediaVersion::query()->create([
        'clinical_media_asset_id' => $asset->id,
        'version_number' => 1,
        'is_original' => true,
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'payload-'.$suffix),
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/payload-'.$suffix.'.jpg',
    ]);

    return $asset;
}
