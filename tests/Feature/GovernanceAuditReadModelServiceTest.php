<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use App\Services\GovernanceAuditReadModelService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

it('returns recent governance-relevant audits in newest-first order', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_SECURITY,
        'action' => AuditLog::ACTION_FAIL,
        'branch_id' => $branch->id,
        'actor_id' => $admin->id,
        'occurred_at' => Carbon::parse('2026-03-28 09:00:00'),
    ]);
    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_INVOICE,
        'action' => AuditLog::ACTION_CANCEL,
        'branch_id' => $branch->id,
        'actor_id' => $admin->id,
        'occurred_at' => Carbon::parse('2026-03-28 10:00:00'),
    ]);
    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_TREATMENT_PLAN,
        'action' => AuditLog::ACTION_UPDATE,
        'branch_id' => $branch->id,
        'actor_id' => $admin->id,
        'occurred_at' => Carbon::parse('2026-03-28 11:00:00'),
    ]);

    $audits = app(GovernanceAuditReadModelService::class)->recentAudits($admin, 10);

    expect($audits)->toHaveCount(2)
        ->and($audits->pluck('entity_type')->all())->toBe([
            AuditLog::ENTITY_INVOICE,
            AuditLog::ENTITY_SECURITY,
        ]);
});

it('applies branch visibility when reading governance audits', function (): void {
    $visibleBranch = Branch::factory()->create();
    $hiddenBranch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $visibleBranch->id,
    ]);
    $manager->assignRole('Manager');
    Permission::findOrCreate('ViewAny:AuditLog', 'web');
    $manager->givePermissionTo('ViewAny:AuditLog');

    $visiblePatient = Patient::factory()->create([
        'first_branch_id' => $visibleBranch->id,
    ]);
    $hiddenPatient = Patient::factory()->create([
        'first_branch_id' => $hiddenBranch->id,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_BRANCH_TRANSFER,
        'action' => AuditLog::ACTION_TRANSFER,
        'branch_id' => $visibleBranch->id,
        'patient_id' => $visiblePatient->id,
        'actor_id' => $manager->id,
        'occurred_at' => Carbon::parse('2026-03-28 12:00:00'),
    ]);
    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_BRANCH_TRANSFER,
        'action' => AuditLog::ACTION_TRANSFER,
        'branch_id' => $hiddenBranch->id,
        'patient_id' => $hiddenPatient->id,
        'actor_id' => $manager->id,
        'occurred_at' => Carbon::parse('2026-03-28 13:00:00'),
    ]);

    $audits = app(GovernanceAuditReadModelService::class)->recentAudits($manager, 10);

    expect($audits)->toHaveCount(1)
        ->and($audits->first()?->patient_id)->toBe($visiblePatient->id)
        ->and($audits->first()?->branch_id)->toBe($visibleBranch->id);
});
