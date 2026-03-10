<?php

use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\TreatmentMaterialUsageService;
use App\Services\TreatmentPlanWorkflowService;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\TreatmentScenarioSeeder;

use function Pest\Laravel\seed;

it('creates treatment scenarios for plan workflow and material usage smoke', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.ident.test')->firstOrFail();
    $this->actingAs($manager);

    $plan = TreatmentPlan::query()->where('title', TreatmentScenarioSeeder::PLAN_TITLE)->firstOrFail();
    $session = TreatmentSession::query()->where('treatment_plan_id', $plan->id)->firstOrFail();
    $material = Material::query()->where('sku', TreatmentScenarioSeeder::MATERIAL_SKU)->firstOrFail();
    $batch = MaterialBatch::query()->where('batch_number', TreatmentScenarioSeeder::MATERIAL_BATCH_NUMBER)->firstOrFail();

    $workflow = app(TreatmentPlanWorkflowService::class);
    $workflow->approve($plan);
    $workflow->start($plan->fresh());

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'used_by' => $manager->id,
    ]);

    expect($material->fresh()->stock_qty)->toBe(8)
        ->and($batch->fresh()->quantity)->toBe(8)
        ->and($usage)->toBeInstanceOf(TreatmentMaterial::class);

    app(TreatmentMaterialUsageService::class)->delete($usage);
    $workflow->complete($plan->fresh());

    expect($material->fresh()->stock_qty)->toBe(10)
        ->and($batch->fresh()->quantity)->toBe(10)
        ->and($plan->fresh()->status)->toBe(TreatmentPlan::STATUS_COMPLETED);
});
