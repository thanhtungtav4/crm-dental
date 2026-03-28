<?php

use App\Filament\Resources\TreatmentPlans\Pages\CreateTreatmentPlan;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\TreatmentPlanWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('forces create payload back to draft before persisting a treatment plan', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $page = app(CreateTreatmentPlan::class);
    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeCreate($data);
    };
    $mutator = $mutator->bindTo($page, CreateTreatmentPlan::class);

    $data = $mutator([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
        'approved_by' => 999,
        'approved_at' => now(),
        'actual_start_date' => now()->toDateString(),
    ]);

    expect($data['status'])->toBe(TreatmentPlan::STATUS_DRAFT)
        ->and($data['approved_by'])->toBeNull()
        ->and($data['approved_at'])->toBeNull()
        ->and($data['actual_start_date'])->toBeNull()
        ->and($data['actual_end_date'])->toBeNull();
});

it('blocks direct treatment plan status mutation outside the workflow service', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    expect(fn () => $plan->update(['status' => TreatmentPlan::STATUS_APPROVED]))
        ->toThrow(ValidationException::class, 'TreatmentPlanWorkflowService');
});

it('blocks forged edit payload from changing workflow-controlled fields directly', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    $page = app(EditTreatmentPlan::class);
    $page->record = $plan;
    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeSave($data);
    };
    $mutator = $mutator->bindTo($page, EditTreatmentPlan::class);

    expect(fn () => $mutator([
        'patient_id' => $patient->id,
        'status' => TreatmentPlan::STATUS_IN_PROGRESS,
        'approved_by' => $manager->id,
    ]))->toThrow(ValidationException::class, 'TreatmentPlanWorkflowService');
});

it('approves starts and completes a treatment plan through the workflow service with audit trail', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    $workflow = app(TreatmentPlanWorkflowService::class);

    $workflow->approve($plan);
    $approvedPlan = $plan->fresh();

    expect($approvedPlan->status)->toBe(TreatmentPlan::STATUS_APPROVED)
        ->and($approvedPlan->approved_by)->toBe($manager->id)
        ->and($approvedPlan->approved_at)->not->toBeNull();

    $workflow->start($approvedPlan);
    $startedPlan = $approvedPlan->fresh();

    expect($startedPlan->status)->toBe(TreatmentPlan::STATUS_IN_PROGRESS)
        ->and($startedPlan->actual_start_date)->not->toBeNull();

    $workflow->complete($startedPlan);
    $completedPlan = $startedPlan->fresh();

    expect($completedPlan->status)->toBe(TreatmentPlan::STATUS_COMPLETED)
        ->and($completedPlan->actual_end_date)->not->toBeNull();

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_PLAN)
        ->where('entity_id', $plan->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->exists())->toBeTrue()
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_TREATMENT_PLAN)
            ->where('entity_id', $plan->id)
            ->where('action', AuditLog::ACTION_COMPLETE)
            ->exists())->toBeTrue();
});

it('records normalized reason and transition metadata when cancelling a treatment plan', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $cancelledPlan = app(TreatmentPlanWorkflowService::class)->cancel(
        $plan,
        '  Benh nhan tam hoan dieu tri  ',
    );

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_PLAN)
        ->where('entity_id', $plan->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($cancelledPlan->status)->toBe(TreatmentPlan::STATUS_CANCELLED)
        ->and($log)->not->toBeNull()
        ->and($log?->metadata['status_from'] ?? null)->toBe(TreatmentPlan::STATUS_APPROVED)
        ->and($log?->metadata['status_to'] ?? null)->toBe(TreatmentPlan::STATUS_CANCELLED)
        ->and($log?->metadata['reason'] ?? null)->toBe('Benh nhan tam hoan dieu tri');
});
