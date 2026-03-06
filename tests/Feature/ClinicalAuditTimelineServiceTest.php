<?php

use App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalAuditTimelineService;
use Carbon\Carbon;

it('builds a unified clinical audit timeline for a patient from audit log and emr audit log', function () {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_CONSENT,
        'entity_id' => 101,
        'action' => AuditLog::ACTION_APPROVE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'consent_type' => 'treatment',
            'consent_version' => 2,
            'status_to' => 'signed',
        ],
        'occurred_at' => Carbon::parse('2026-03-06 10:00:00'),
    ]);

    EmrAuditLog::factory()->create([
        'entity_type' => EmrAuditLog::ENTITY_CLINICAL_RESULT,
        'entity_id' => 202,
        'action' => EmrAuditLog::ACTION_FINALIZE,
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'actor_id' => $actor->id,
        'context' => [
            'result_code' => 'LAB-001',
            'status_to' => 'final',
        ],
        'occurred_at' => Carbon::parse('2026-03-06 11:00:00'),
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_PAYMENT,
        'entity_id' => 303,
        'action' => AuditLog::ACTION_UPDATE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'occurred_at' => Carbon::parse('2026-03-06 12:00:00'),
    ]);

    $entries = app(ClinicalAuditTimelineService::class)->timelineEntriesForPatient($patient, 10);

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('title')->all())->toBe([
            'Kết quả cận lâm sàng',
            'Consent đã ký',
        ])
        ->and($entries->map(fn (array $entry): ?string => data_get($entry, 'meta.Nguồn audit'))->all())->toBe([
            'EmrAuditLog',
            'AuditLog',
        ]);
});

it('injects unified clinical audit entries into the patient activity timeline widget', function () {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_CONSENT,
        'entity_id' => 401,
        'action' => AuditLog::ACTION_APPROVE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'consent_type' => 'imaging',
            'consent_version' => 1,
            'status_to' => 'signed',
        ],
        'occurred_at' => Carbon::parse('2026-03-06 15:00:00'),
    ]);

    $widget = new PatientActivityTimelineWidget;
    $widget->record = $patient;

    $activities = $widget->getActivities();

    expect($activities->pluck('title')->all())->toContain('Consent đã ký')
        ->and($activities->where('type', 'clinical_audit')->isNotEmpty())->toBeTrue();
});
