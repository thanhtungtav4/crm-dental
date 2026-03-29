<?php

use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\PatientTreatmentPlanReadModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds patient treatment plan section data through the shared read model', function (): void {
    $patient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $categoryA = ServiceCategory::query()->create([
        'name' => 'Điều trị tổng quát',
        'code' => 'DTTQ',
        'active' => true,
        'sort_order' => 1,
    ]);
    $categoryB = ServiceCategory::query()->create([
        'name' => 'Phục hình',
        'code' => 'PH',
        'active' => true,
        'sort_order' => 2,
    ]);

    $serviceA = Service::query()->create([
        'category_id' => $categoryA->id,
        'name' => 'Trám composite',
        'code' => 'TRAM-COMPOSITE',
        'unit' => 'răng',
        'default_price' => 150_000,
        'active' => true,
        'sort_order' => 1,
    ]);
    $serviceB = Service::query()->create([
        'category_id' => $categoryB->id,
        'name' => 'Bọc sứ thẩm mỹ',
        'code' => 'BOC-SU',
        'unit' => 'răng',
        'default_price' => 2_000_000,
        'active' => true,
        'sort_order' => 1,
    ]);

    $toothCondition = ToothCondition::query()->create([
        'code' => 'K02-RM',
        'name' => '(K02) Sâu răng',
        'category' => 'Bệnh lý',
    ]);

    $diagnosisRecord = PatientToothCondition::query()->create([
        'patient_id' => $patient->id,
        'tooth_number' => '11',
        'tooth_condition_id' => $toothCondition->id,
        'treatment_status' => PatientToothCondition::STATUS_CURRENT,
    ]);

    $otherDiagnosisRecord = PatientToothCondition::query()->create([
        'patient_id' => $otherPatient->id,
        'tooth_number' => '21',
        'tooth_condition_id' => $toothCondition->id,
        'treatment_status' => PatientToothCondition::STATUS_CURRENT,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);
    $otherPlan = TreatmentPlan::factory()->create([
        'patient_id' => $otherPatient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $otherPatient->first_branch_id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $pendingItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $serviceA->id,
        'name' => $serviceA->name,
        'diagnosis_ids' => [$diagnosisRecord->id],
        'quantity' => 2,
        'price' => 100_000,
        'discount_amount' => 10_000,
        'discount_percent' => 0,
        'vat_amount' => 5_000,
        'final_amount' => 195_000,
        'status' => PlanItem::STATUS_PENDING,
        'is_completed' => false,
    ]);

    $completedItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $serviceB->id,
        'name' => $serviceB->name,
        'diagnosis_ids' => [$diagnosisRecord->id],
        'quantity' => 1,
        'price' => 100_000,
        'discount_amount' => 0,
        'discount_percent' => 0,
        'vat_amount' => 5_000,
        'final_amount' => 105_000,
        'status' => PlanItem::STATUS_COMPLETED,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
        'is_completed' => true,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $otherPlan->id,
        'service_id' => $serviceA->id,
        'name' => 'Ngoài phạm vi',
        'diagnosis_ids' => [$otherDiagnosisRecord->id],
        'quantity' => 4,
        'price' => 999_000,
        'final_amount' => 3_996_000,
    ]);

    $service = app(PatientTreatmentPlanReadModelService::class);

    $sectionData = $service->sectionData(
        patientId: $patient->id,
        selectedCategoryId: $categoryA->id,
        procedureSearch: 'Trám',
    );

    expect($sectionData['planItems']->pluck('id')->all())
        ->toBe([$completedItem->id, $pendingItem->id])
        ->and($sectionData['diagnosisMap']->keys()->all())->toBe([$diagnosisRecord->id])
        ->and($sectionData['diagnosisRecords']->pluck('id')->all())->toBe([$diagnosisRecord->id])
        ->and($sectionData['diagnosisOptions'])->toBe([
            $diagnosisRecord->id => 'Răng 11 - (K02) Sâu răng',
        ])
        ->and($sectionData['diagnosisDetails'])->toBe([
            $diagnosisRecord->id => [
                'tooth_number' => '11',
                'condition_name' => '(K02) Sâu răng',
            ],
        ])
        ->and($sectionData['categories']->pluck('id')->all())->toBe([$categoryA->id, $categoryB->id])
        ->and($sectionData['services']->pluck('id')->all())->toBe([$serviceA->id])
        ->and($sectionData['estimatedTotal'])->toBe(300000.0)
        ->and($sectionData['discountTotal'])->toBe(10000.0)
        ->and($sectionData['totalCost'])->toBe(300000.0)
        ->and($sectionData['completedCost'])->toBe(105000.0)
        ->and($sectionData['pendingCost'])->toBe(195000.0);

    $selectedServiceIds = $service->servicesByIds([$serviceA->id, $serviceB->id, $serviceA->id, 0])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($selectedServiceIds)->toBe([
        $serviceA->id,
        $serviceB->id,
    ]);
});
