<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use App\Models\User;
use App\Services\PatientInsightReportReadModelService;

it('summarizes patient and risk insights within selected branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctorA = User::factory()->create(['branch_id' => $branchA->id]);
    $doctorA->assignRole('Doctor');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'primary_doctor_id' => $doctorA->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patientA->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 72,
        'churn_risk_score' => 41,
        'risk_level' => PatientRiskProfile::LEVEL_HIGH,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patientB->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 15,
        'churn_risk_score' => 18,
        'risk_level' => PatientRiskProfile::LEVEL_LOW,
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    Note::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'user_id' => $doctorA->id,
        'type' => 'care',
        'content' => 'Follow up high risk',
        'care_type' => 'risk_high_follow_up',
        'care_status' => Note::CARE_STATUS_IN_PROGRESS,
        'care_at' => now(),
    ]);

    Note::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'user_id' => null,
        'type' => 'care',
        'content' => 'Hidden branch follow up',
        'care_type' => 'risk_high_follow_up',
        'care_status' => Note::CARE_STATUS_IN_PROGRESS,
        'care_at' => now(),
    ]);

    $service = app(PatientInsightReportReadModelService::class);

    expect($service->patientSummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe(['total_patients' => 1])
        ->and($service->riskSummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe([
            'total' => 1,
            'high' => 1,
            'medium' => 0,
            'low' => 0,
            'average_no_show' => 72.0,
            'average_churn' => 41.0,
            'active_intervention_tickets' => 1,
        ]);
});

it('returns empty patient and risk readers for inaccessible branch selections', function (): void {
    $service = app(PatientInsightReportReadModelService::class);

    expect($service->patientBreakdownQuery([])->get())->toHaveCount(0)
        ->and($service->riskProfileQuery([])->get())->toHaveCount(0)
        ->and($service->patientSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total_patients' => 0,
        ])
        ->and($service->riskSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'average_no_show' => 0.0,
            'average_churn' => 0.0,
            'active_intervention_tickets' => 0,
        ]);
});
