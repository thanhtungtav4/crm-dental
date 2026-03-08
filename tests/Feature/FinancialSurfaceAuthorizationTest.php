<?php

use App\Filament\Pages\DentalApp;
use App\Filament\Pages\DentalChainReport;
use App\Filament\Pages\FinancialDashboard;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ReceiptExpense;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('blocks doctor and cskh roles from finance and system placeholder surfaces', function (string $role, callable $urlResolver): void {
    $branch = Branch::factory()->create();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get($urlResolver())
        ->assertForbidden();
})->with([
    'doctor dashboard' => ['Doctor', fn (): string => FinancialDashboard::getUrl()],
    'doctor receipts index' => ['Doctor', fn (): string => ReceiptsExpenseResource::getUrl('index')],
    'doctor firewall' => ['Doctor', fn (): string => route('filament.admin.resources.firewall-ips.index', absolute: false)],
    'doctor branch report' => ['Doctor', fn (): string => DentalChainReport::getUrl()],
    'doctor dental app' => ['Doctor', fn (): string => DentalApp::getUrl()],
    'cskh dashboard' => ['CSKH', fn (): string => FinancialDashboard::getUrl()],
    'cskh receipts create' => ['CSKH', fn (): string => ReceiptsExpenseResource::getUrl('create')],
    'cskh firewall' => ['CSKH', fn (): string => route('filament.admin.resources.firewall-ips.index', absolute: false)],
    'cskh branch report' => ['CSKH', fn (): string => DentalChainReport::getUrl()],
    'cskh dental app' => ['CSKH', fn (): string => DentalApp::getUrl()],
]);

it('allows managers into branch-level finance pages but keeps firewall admin-only', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(FinancialDashboard::getUrl())
        ->assertOk();

    $this->actingAs($manager)
        ->get(ReceiptsExpenseResource::getUrl('index'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(ReceiptsExpenseResource::getUrl('create'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(DentalChainReport::getUrl())
        ->assertOk();

    $this->actingAs($manager)
        ->get(DentalApp::getUrl())
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('filament.admin.resources.firewall-ips.index', absolute: false))
        ->assertForbidden();
});

it('allows only admins into the firewall resource', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.firewall-ips.index', absolute: false))
        ->assertOk();
});

it('scopes overdue invoice widgets to the manager branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $patientA = Patient::factory()->create([
        'first_branch_id' => $branchA->id,
    ]);
    $patientB = Patient::factory()->create([
        'first_branch_id' => $branchB->id,
    ]);

    $invoiceA = Invoice::factory()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-SCOPE-A',
        'total_amount' => 5000000,
        'paid_amount' => 1000000,
        'status' => Invoice::STATUS_OVERDUE,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $invoiceB = Invoice::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-SCOPE-B',
        'total_amount' => 7000000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_OVERDUE,
        'due_date' => now()->subDays(7)->toDateString(),
    ]);

    $this->actingAs($manager);

    Livewire::test(OverdueInvoicesWidget::class)
        ->assertCanSeeTableRecords([$invoiceA])
        ->assertCanNotSeeTableRecords([$invoiceB]);
});

it('rejects receipt expense writes outside the manager branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    expect(fn () => ReceiptExpense::query()->create([
        'clinic_id' => $branchB->id,
        'voucher_type' => 'expense',
        'voucher_date' => now()->toDateString(),
        'amount' => 250000,
        'payment_method' => 'cash',
        'status' => 'draft',
    ]))->toThrow(ValidationException::class, 'phiếu thu/chi ở chi nhánh này');
});

it('hides receipt expense records from other branches at the route binding layer', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $receiptExpense = ReceiptExpense::query()->create([
        'clinic_id' => $branchB->id,
        'voucher_type' => 'expense',
        'voucher_date' => now()->toDateString(),
        'amount' => 450000,
        'payment_method' => 'cash',
        'status' => 'draft',
    ]);

    $this->actingAs($manager)
        ->get(ReceiptsExpenseResource::getUrl('edit', ['record' => $receiptExpense]))
        ->assertNotFound();
});
