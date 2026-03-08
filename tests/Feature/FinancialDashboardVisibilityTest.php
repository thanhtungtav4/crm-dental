<?php

use App\Filament\Pages\FinancialDashboard;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Filament\Widgets\QuickFinancialStatsWidget;
use App\Filament\Widgets\RevenueOverviewWidget;
use App\Models\Branch;
use App\Models\User;
use Filament\Pages\Dashboard;

it('hides finance widgets from the default dashboard for doctor and cskh roles', function (string $role): void {
    $branch = Branch::factory()->create();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertDontSeeLivewire(RevenueOverviewWidget::class)
        ->assertDontSeeLivewire(QuickFinancialStatsWidget::class)
        ->assertDontSeeLivewire(OverdueInvoicesWidget::class);

    expect(RevenueOverviewWidget::canView())->toBeFalse()
        ->and(QuickFinancialStatsWidget::canView())->toBeFalse()
        ->and(OverdueInvoicesWidget::canView())->toBeFalse();
})->with([
    'doctor' => 'Doctor',
    'cskh' => 'CSKH',
]);

it('shows finance widgets on the finance dashboard for managers', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(FinancialDashboard::getUrl())
        ->assertOk()
        ->assertSeeLivewire(RevenueOverviewWidget::class)
        ->assertSeeLivewire(QuickFinancialStatsWidget::class)
        ->assertSeeLivewire(OverdueInvoicesWidget::class);

    expect(RevenueOverviewWidget::canView())->toBeTrue()
        ->and(QuickFinancialStatsWidget::canView())->toBeTrue()
        ->and(OverdueInvoicesWidget::canView())->toBeTrue();
});
