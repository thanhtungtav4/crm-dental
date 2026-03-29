<?php

use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\PatientTreatmentPlanDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('syncs diagnosis from the latest exam and saves draft items into the latest treatment plan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create();

    $serviceCategory = ServiceCategory::query()->create([
        'name' => 'Điều trị tổng quát',
        'code' => 'DTTQ',
        'active' => true,
        'sort_order' => 1,
    ]);

    $service = Service::query()->create([
        'category_id' => $serviceCategory->id,
        'name' => 'Trám răng cửa',
        'code' => 'TRAM-RC',
        'unit' => 'răng',
        'default_price' => 250000,
        'active' => true,
        'sort_order' => 1,
    ]);

    $toothCondition = ToothCondition::query()->create([
        'code' => 'K02-RM',
        'name' => '(K02) Sâu răng',
        'category' => 'Bệnh lý',
    ]);

    ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $admin->id,
        'branch_id' => $patient->first_branch_id,
        'date' => now()->toDateString(),
        'chief_complaint' => 'Đau răng',
        'tooth_diagnosis_data' => [
            '11' => [
                'conditions' => ['K02'],
                'notes' => 'Sâu mặt ngoài',
            ],
        ],
    ]);

    $existingPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $admin->id,
        'branch_id' => $patient->first_branch_id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    actingAs($admin);

    $draftService = app(PatientTreatmentPlanDraftService::class);

    $draftService->prepareDraft($patient->id, $admin->id);

    $diagnosis = PatientToothCondition::query()
        ->where('patient_id', $patient->id)
        ->sole();

    expect($diagnosis->tooth_number)->toBe('11')
        ->and($diagnosis->tooth_condition_id)->toBe($toothCondition->id)
        ->and($diagnosis->notes)->toBe('Sâu mặt ngoài')
        ->and($diagnosis->treatment_status)->toBe(PatientToothCondition::STATUS_CURRENT)
        ->and($diagnosis->diagnosed_by)->toBe($admin->id);

    $resolvedPlan = $draftService->saveDraftItems($patient->id, [[
        'service_id' => $service->id,
        'service_name' => $service->name,
        'diagnosis_ids' => [$diagnosis->id],
        'quantity' => 2,
        'price' => 100000,
        'discount_percent' => 10,
        'discount_amount' => 0,
        'notes' => 'Ưu tiên làm sớm',
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'approval_decline_reason' => '',
    ]], $admin->id);

    $planItem = PlanItem::query()
        ->where('treatment_plan_id', $existingPlan->id)
        ->sole();

    expect($resolvedPlan->is($existingPlan))->toBeTrue()
        ->and($planItem->service_id)->toBe($service->id)
        ->and($planItem->diagnosis_ids)->toBe([$diagnosis->id])
        ->and($planItem->tooth_ids)->toBe(['11'])
        ->and($planItem->tooth_number)->toBe('11')
        ->and((float) $planItem->discount_amount)->toBe(20000.0)
        ->and((float) $planItem->final_amount)->toBe(180000.0)
        ->and($planItem->approval_status)->toBe(PlanItem::APPROVAL_APPROVED)
        ->and($planItem->patient_approved)->toBeTrue()
        ->and($planItem->notes)->toBe('Ưu tiên làm sớm');
});
