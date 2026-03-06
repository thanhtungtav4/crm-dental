<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\User;
use App\Services\PatientWalletService;
use App\Support\ActionPermission;
use Illuminate\Validation\ValidationException;

it('requires dedicated wallet adjust action permission for manual wallet adjustment', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo([
        'ViewAny:PatientWallet',
        'View:PatientWallet',
        'Update:PatientWallet',
    ]);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 200_000,
    ]);

    $this->actingAs($user);

    expect(fn () => app(PatientWalletService::class)->adjustBalance(
        wallet: $wallet,
        amount: 50_000,
        note: 'Manual correction',
        actorId: $user->id,
    ))->toThrow(ValidationException::class, 'điều chỉnh số dư ví');
});

it('records audit log and ledger entry when manager adjusts wallet', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 300_000,
    ]);

    $this->actingAs($manager);

    $entry = app(PatientWalletService::class)->adjustBalance(
        wallet: $wallet,
        amount: -50_000,
        note: 'Điều chỉnh sai lệch đối soát',
        actorId: $manager->id,
    );

    $wallet->refresh();

    expect((float) $wallet->balance)->toEqualWithDelta(250_000, 0.01)
        ->and((float) $entry->balance_before)->toEqualWithDelta(300_000, 0.01)
        ->and((float) $entry->balance_after)->toEqualWithDelta(250_000, 0.01);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PATIENT_WALLET)
        ->where('entity_id', $wallet->id)
        ->where('action', AuditLog::ACTION_UPDATE)
        ->latest('id')
        ->first();

    expect($manager->can(ActionPermission::WALLET_ADJUST))->toBeTrue()
        ->and($log)->not->toBeNull()
        ->and($log?->branch_id)->toBe($branch->id)
        ->and($log?->patient_id)->toBe($patient->id)
        ->and($log?->metadata['wallet_entry_id'] ?? null)->toBe($entry->id)
        ->and((float) ($log?->metadata['adjustment_amount'] ?? 0))->toEqualWithDelta(-50_000, 0.01);
});
