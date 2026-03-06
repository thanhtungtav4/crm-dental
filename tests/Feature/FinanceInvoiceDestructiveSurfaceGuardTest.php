<?php

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive invoice surfaces from finance UI', function (): void {
    $editPage = File::get(app_path('Filament/Resources/Invoices/Pages/EditInvoice.php'));
    $table = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');
});

it('denies invoice delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $invoice = Invoice::factory()->create([
        'branch_id' => $branch->id,
    ]);

    expect($admin->can('delete', $invoice))->toBeFalse()
        ->and($admin->can('restore', $invoice))->toBeFalse()
        ->and($admin->can('forceDelete', $invoice))->toBeFalse();
});

it('blocks direct invoice delete attempts at model layer', function (): void {
    $invoice = Invoice::factory()->create();

    expect(fn () => $invoice->delete())
        ->toThrow(ValidationException::class, 'khong ho tro xoa');
});

it('blocks direct invoice force delete attempts at model layer', function (): void {
    $invoice = Invoice::factory()->create();

    expect(fn () => $invoice->forceDelete())
        ->toThrow(ValidationException::class, 'khong ho tro xoa');
});
