<?php

use App\Models\Consent;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks treatment phase progression when required consent is missing', function () {
    [$item] = makePlanItemRequiringConsent();

    expect(fn () => $item->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]))->toThrow(ValidationException::class, 'Thiếu consent hợp lệ');
});

it('allows treatment phase progression after signed consent is present', function () {
    [$item, $patient, $doctor] = makePlanItemRequiringConsent();

    Consent::create([
        'patient_id' => $patient->id,
        'service_id' => $item->service_id,
        'plan_item_id' => $item->id,
        'consent_type' => 'high_risk',
        'consent_version' => 'v1',
        'status' => Consent::STATUS_SIGNED,
        'signed_by' => $doctor->id,
        'signed_at' => now(),
    ]);

    $item->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    expect($item->fresh()->status)->toBe(PlanItem::STATUS_IN_PROGRESS);
});

function makePlanItemRequiringConsent(): array
{
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $service = Service::query()->create([
        'name' => 'Implant high risk',
        'default_price' => 12000000,
        'requires_consent' => true,
        'active' => true,
    ]);

    $plan = TreatmentPlan::create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'title' => 'Kế hoạch implant',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $item = PlanItem::create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Cấy implant răng 16',
        'service_id' => $service->id,
        'quantity' => 1,
        'price' => 12000000,
        'estimated_cost' => 12000000,
        'actual_cost' => 0,
        'required_visits' => 2,
        'completed_visits' => 0,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
    ]);

    return [$item, $patient, $doctor];
}
