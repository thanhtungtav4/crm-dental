<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('hydrates structured context from explicit arguments and metadata fallback', function (): void {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $occurredAt = now()->subMinutes(15)->startOfSecond();

    $explicitLog = AuditLog::record(
        entityType: AuditLog::ENTITY_PAYMENT,
        entityId: 123,
        action: AuditLog::ACTION_CREATE,
        actorId: $actor->id,
        metadata: [
            'branch_id' => 999999,
            'patient_id' => 999999,
        ],
        branchId: $branch->id,
        patientId: $patient->id,
        occurredAt: $occurredAt,
    );

    $fallbackLog = AuditLog::record(
        entityType: AuditLog::ENTITY_INVOICE,
        entityId: 456,
        action: AuditLog::ACTION_UPDATE,
        actorId: $actor->id,
        metadata: [
            'patient_id' => $patient->id,
        ],
    );

    expect($explicitLog->refresh()->branch_id)->toBe($branch->id)
        ->and($explicitLog->patient_id)->toBe($patient->id)
        ->and($explicitLog->occurred_at?->toDateTimeString())->toBe($occurredAt->toDateTimeString());

    expect($fallbackLog->refresh()->patient_id)->toBe($patient->id)
        ->and($fallbackLog->branch_id)->toBe($branch->id)
        ->and($fallbackLog->occurred_at)->not->toBeNull();
});

it('keeps audit logs immutable against update and delete', function (): void {
    $log = AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_SECURITY,
        'entity_id' => 999,
        'action' => AuditLog::ACTION_FAIL,
    ]);

    expect(fn () => $log->update(['action' => AuditLog::ACTION_BLOCK]))
        ->toThrow(ValidationException::class);

    expect(fn () => $log->delete())
        ->toThrow(ValidationException::class);

    expect($log->fresh()?->action)->toBe(AuditLog::ACTION_FAIL);
});

it('supports structured patient and branch scopes for operational queries', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $visibleLog = AuditLog::record(
        entityType: AuditLog::ENTITY_APPOINTMENT,
        entityId: 1,
        action: AuditLog::ACTION_RESCHEDULE,
        metadata: [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ],
    );

    $hiddenLog = AuditLog::record(
        entityType: AuditLog::ENTITY_APPOINTMENT,
        entityId: 2,
        action: AuditLog::ACTION_CANCEL,
        metadata: [
            'branch_id' => $otherBranch->id,
        ],
    );

    expect(AuditLog::query()->forPatient($patient->id)->pluck('id')->all())
        ->toContain($visibleLog->id)
        ->not->toContain($hiddenLog->id);

    expect(AuditLog::query()->forBranch($branch->id)->pluck('id')->all())
        ->toContain($visibleLog->id)
        ->not->toContain($hiddenLog->id);
});
