<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceiptExpense;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

use function Pest\Laravel\seed;

it('pre-enrolls sensitive demo accounts for mfa and exposes cskh as front-office persona', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.nhakhoaanphuc.test')
        ->firstOrFail()
        ->load('breezySession');

    $manager = User::query()
        ->where('email', 'manager.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail()
        ->load('breezySession');

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    expect($admin->two_factor_confirmed_at)->not->toBeNull()
        ->and($manager->two_factor_confirmed_at)->not->toBeNull()
        ->and($admin->breezySession?->two_factor_confirmed_at)->not->toBeNull()
        ->and($manager->breezySession?->two_factor_confirmed_at)->not->toBeNull()
        ->and($admin->breezySession?->two_factor_recovery_codes)->toBe(LocalDemoDataSeeder::demoMfaRecoveryCodesFor($admin->email))
        ->and($manager->breezySession?->two_factor_recovery_codes)->toBe(LocalDemoDataSeeder::demoMfaRecoveryCodesFor($manager->email))
        ->and($cskh->two_factor_confirmed_at)->toBeNull()
        ->and($cskh->name)->toContain('Tu van / Le tan');
});

it('aligns the cskh role with front-office work while keeping finance and system pages closed', function (): void {
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    expect($user->can('Create:Customer'))->toBeTrue()
        ->and($user->can('Update:Customer'))->toBeTrue()
        ->and($user->can('Create:Patient'))->toBeTrue()
        ->and($user->can('Create:Appointment'))->toBeTrue()
        ->and($user->can('Update:Appointment'))->toBeTrue()
        ->and($user->can('ViewAny:Invoice'))->toBeFalse()
        ->and($user->can('ViewAny:ReceiptExpense'))->toBeFalse()
        ->and($user->can('View:FinancialDashboard'))->toBeFalse()
        ->and($user->can('View:DentalApp'))->toBeFalse();
});

it('seeds qa scenarios for leads, appointments, invoices, and receipt expense states', function (): void {
    seed(LocalDemoDataSeeder::class);

    expect(Customer::query()->where('status', 'contacted')->exists())->toBeTrue()
        ->and(Customer::query()->where('status', 'converted')->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_NO_SHOW)->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', Appointment::STATUS_RESCHEDULED)->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', Invoice::STATUS_PARTIAL)->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->exists())->toBeTrue();

    if (! DatabaseSchema::hasTable('receipts_expense')) {
        return;
    }

    expect(ReceiptExpense::query()->where('status', 'draft')->exists())->toBeTrue()
        ->and(ReceiptExpense::query()->where('status', 'approved')->exists())->toBeTrue()
        ->and(ReceiptExpense::query()->where('status', 'posted')->exists())->toBeTrue();
});
