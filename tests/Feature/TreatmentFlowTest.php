<?php

use App\Models\Material;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\TreatmentMaterial;
use App\Models\InventoryTransaction;
use App\Models\User;

it('creates plan → session → materials and updates stock & logs', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => null,
        'status' => 'in_progress',
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $user->id,
        'status' => 'done',
    ]);

    $material = Material::factory()->create([
        'stock_qty' => 100,
    ]);

    $usage = TreatmentMaterial::create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'quantity' => 5,
        'cost' => 0,
        'used_by' => $user->id,
    ]);

    expect($material->fresh()->stock_qty)->toBe(95)
        ->and(InventoryTransaction::where('material_id', $material->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'out')
            ->exists())->toBeTrue();

    // Deleting usage should restore stock and log adjust
    $usage->delete();

    expect($material->fresh()->stock_qty)->toBe(100)
        ->and(InventoryTransaction::where('material_id', $material->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'adjust')
            ->exists())->toBeTrue();
});


