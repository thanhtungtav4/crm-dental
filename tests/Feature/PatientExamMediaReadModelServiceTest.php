<?php

use App\Models\ClinicalMediaAsset;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientExamMediaReadModelService;

it('builds patient exam media timeline, phase summary, and evidence checklist from a shared read model', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $activeSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-25',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $olderSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-20',
        'status' => ExamSession::STATUS_COMPLETED,
    ]);

    $currentAsset = ClinicalMediaAsset::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_by' => $doctor->id,
        'exam_session_id' => $activeSession->id,
        'captured_at' => '2026-03-25 10:00:00',
        'phase' => 'pre',
        'checksum_sha256' => null,
        'meta' => [
            'indication_type' => 'panorama',
        ],
    ]);

    $olderAsset = ClinicalMediaAsset::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'captured_by' => $doctor->id,
        'exam_session_id' => $olderSession->id,
        'captured_at' => '2026-03-20 08:00:00',
        'phase' => 'followup',
        'meta' => [
            'indication_type' => 'xray',
        ],
    ]);

    $payload = app(PatientExamMediaReadModelService::class)->build(
        patient: $patient,
        activeSessionId: $activeSession->id,
        selectedIndications: ['panorama', 'xray'],
        indicationImages: [
            'panorama' => ['clinical-notes/panorama-1.png'],
            'xray' => [],
        ],
        indicationTypes: [
            'panorama' => 'Panorama',
            'xray' => 'X-Quang',
        ],
    );

    expect($payload['mediaPhaseSummary'])->toBe([
        'pre' => 1,
        'followup' => 1,
    ]);

    expect($payload['mediaTimeline'])->toHaveCount(2)
        ->and($payload['mediaTimeline'][0])->toMatchArray([
            'id' => $currentAsset->id,
            'phase' => 'pre',
            'exam_session_id' => $activeSession->id,
        ])
        ->and($payload['mediaTimeline'][0]['view_url'])->toContain('/clinical-media/'.$currentAsset->id.'/view')
        ->and($payload['mediaTimeline'][0]['download_url'])->toContain('/clinical-media/'.$currentAsset->id.'/download')
        ->and($payload['mediaTimeline'][1])->toMatchArray([
            'id' => $olderAsset->id,
            'phase' => 'followup',
            'exam_session_id' => $olderSession->id,
        ]);

    expect($payload['evidenceChecklist'])->toMatchArray([
        'required' => 2,
        'fulfilled' => 1,
        'completion_percent' => 50,
        'missing_labels' => ['X-Quang'],
    ])
        ->and($payload['evidenceChecklist']['quality_warnings'])->toContain(
            'Có ảnh chưa có checksum, cần kiểm tra integrity dữ liệu.',
        )
        ->and($payload['evidenceChecklist']['quality_warnings'])->not->toContain(
            'Phiếu khám đã chọn chỉ định nhưng chưa có bằng chứng ảnh lâm sàng.',
        );
});
