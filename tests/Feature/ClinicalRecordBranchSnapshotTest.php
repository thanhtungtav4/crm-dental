<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Policies\NotePolicy;

it('keeps note branch authorization stable after patient transfer', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $managerB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $managerB->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $note = Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $managerA->id,
        'type' => Note::TYPE_GENERAL,
        'content' => 'Nhắc tái khám sau điều trị.',
    ]);

    expect($note->resolveBranchId())->toBe($branchA->id);

    $patient->update([
        'first_branch_id' => $branchB->id,
    ]);

    $policy = app(NotePolicy::class);
    $refreshedNote = $note->fresh();

    expect($refreshedNote)->not->toBeNull()
        ->and($refreshedNote?->resolveBranchId())->toBe($branchA->id)
        ->and($policy->view($managerA, $refreshedNote))->toBeTrue()
        ->and($policy->view($managerB, $refreshedNote))->toBeFalse();
});

it('keeps consent branch snapshot and audit metadata stable after patient transfer', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Nhổ răng khôn',
        'quantity' => 1,
        'price' => 1200000,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    $this->actingAs($manager);

    $consent = Consent::query()->create([
        'patient_id' => $patient->id,
        'plan_item_id' => $planItem->id,
        'consent_type' => 'high_risk',
        'consent_version' => 'v1',
        'status' => Consent::STATUS_PENDING,
    ]);

    $consent->update([
        'status' => Consent::STATUS_SIGNED,
        'signed_by' => $manager->id,
    ]);

    $patient->update([
        'first_branch_id' => $branchB->id,
    ]);

    $refreshedConsent = $consent->fresh();
    $consentAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CONSENT)
        ->where('entity_id', $consent->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->latest('id')
        ->first();

    expect($refreshedConsent)->not->toBeNull()
        ->and($refreshedConsent?->resolveBranchId())->toBe($branchA->id)
        ->and((int) data_get($consentAudit?->metadata, 'branch_id'))->toBe($branchA->id);
});
