<?php

use App\Models\Branch;
use App\Models\ReceiptExpense;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('denies receipt expense delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $receiptExpense = ReceiptExpense::query()->create([
        'clinic_id' => $branch->id,
        'voucher_type' => 'expense',
        'voucher_date' => now()->toDateString(),
        'amount' => 450000,
        'payment_method' => 'cash',
        'status' => ReceiptExpense::STATUS_DRAFT,
    ]);

    expect($admin->can('delete', $receiptExpense))->toBeFalse()
        ->and($admin->can('deleteAny', ReceiptExpense::class))->toBeFalse()
        ->and($admin->can('restore', $receiptExpense))->toBeFalse()
        ->and($admin->can('forceDelete', $receiptExpense))->toBeFalse()
        ->and($admin->can('restoreAny', ReceiptExpense::class))->toBeFalse()
        ->and($admin->can('forceDeleteAny', ReceiptExpense::class))->toBeFalse();
});

it('blocks direct receipt expense delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create();

    $receiptExpense = ReceiptExpense::query()->create([
        'clinic_id' => $branch->id,
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'amount' => 320000,
        'payment_method' => 'cash',
        'status' => ReceiptExpense::STATUS_DRAFT,
    ]);

    expect(fn () => $receiptExpense->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
