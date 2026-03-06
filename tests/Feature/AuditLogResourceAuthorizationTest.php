<?php

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('forbids doctor from accessing the audit log resource', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $auditLog = AuditLog::factory()->create();

    $this->actingAs($doctor)
        ->get(route('filament.admin.resources.audit-logs.index'))
        ->assertForbidden();

    expect($doctor->can('view', $auditLog))->toBeFalse();

    $this->actingAs($doctor)
        ->get(route('filament.admin.resources.audit-logs.view', ['record' => $auditLog]))
        ->assertNotFound();
});

it('forbids manager without delegated audit permissions from accessing the audit log resource', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $auditLog = AuditLog::factory()->create();

    $this->actingAs($manager)
        ->get(route('filament.admin.resources.audit-logs.index'))
        ->assertForbidden();

    expect($manager->can('view', $auditLog))->toBeFalse();

    $this->actingAs($manager)
        ->get(route('filament.admin.resources.audit-logs.view', ['record' => $auditLog]))
        ->assertNotFound();
});

it('allows admin to access audit log list and view pages', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $auditLog = AuditLog::factory()->create();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.audit-logs.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.audit-logs.view', ['record' => $auditLog]))
        ->assertOk();
});

it('scopes audit log query and record view to accessible branches for delegated audit viewers', function (): void {
    $accessibleBranch = Branch::factory()->create(['active' => true]);
    $otherBranch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $accessibleBranch->id,
    ]);
    $manager->assignRole('Manager');

    Permission::findOrCreate('ViewAny:AuditLog', 'web');
    Permission::findOrCreate('View:AuditLog', 'web');
    $manager->givePermissionTo(['ViewAny:AuditLog', 'View:AuditLog']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $visibleAuditLog = AuditLog::factory()->create([
        'metadata' => [
            'branch_id' => $accessibleBranch->id,
        ],
    ]);

    $hiddenAuditLog = AuditLog::factory()->create([
        'metadata' => [
            'branch_id' => $otherBranch->id,
        ],
    ]);

    $this->actingAs($manager);

    expect(AuditLogResource::getEloquentQuery()->pluck('audit_logs.id')->all())
        ->toContain($visibleAuditLog->id)
        ->not->toContain($hiddenAuditLog->id);

    expect($manager->can('view', $visibleAuditLog))->toBeTrue()
        ->and($manager->can('view', $hiddenAuditLog))->toBeFalse();

    $this->get(route('filament.admin.resources.audit-logs.view', ['record' => $visibleAuditLog]))
        ->assertOk();

    $this->get(route('filament.admin.resources.audit-logs.view', ['record' => $hiddenAuditLog]))
        ->assertNotFound();
});
