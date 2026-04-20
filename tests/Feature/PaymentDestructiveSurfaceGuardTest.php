<?php

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive payment surfaces from the finance ui', function (): void {
    $editPage = File::get(app_path('Filament/Resources/Payments/Pages/EditPayment.php'));
    $table = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('DeleteAction');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('DeleteBulkAction');
});

it('denies payment delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
    ]);

    expect($admin->can('delete', $payment))->toBeFalse()
        ->and($admin->can('deleteAny', Payment::class))->toBeFalse()
        ->and($admin->can('restore', $payment))->toBeFalse()
        ->and($admin->can('forceDelete', $payment))->toBeFalse()
        ->and($admin->can('restoreAny', Payment::class))->toBeFalse()
        ->and($admin->can('forceDeleteAny', Payment::class))->toBeFalse();
});

it('blocks direct payment delete attempts at model layer', function (): void {
    $payment = Payment::factory()->create();

    expect(fn () => $payment->delete())
        ->toThrow(ValidationException::class, 'tạo phiếu hoàn thay vì xóa trực tiếp');
});
