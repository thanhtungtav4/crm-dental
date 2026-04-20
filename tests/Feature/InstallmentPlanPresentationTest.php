<?php

use App\Models\InstallmentPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('builds installment schedule modal presentation from installment plan state', function (): void {
    Carbon::setTestNow('2026-04-07 10:00:00');

    $plan = new InstallmentPlan([
        'financed_amount' => 1_200_000,
        'remaining_amount' => 700_000,
        'schedule' => [
            [
                'installment_number' => 1,
                'due_date' => '2026-04-01',
                'amount' => 400_000,
                'paid_amount' => 0,
                'status' => 'pending',
            ],
            [
                'installment_number' => 2,
                'due_date' => '2026-04-10',
                'amount' => 400_000,
                'paid_amount' => 100_000,
                'status' => 'pending',
            ],
            [
                'installment_number' => 3,
                'due_date' => '2026-05-01',
                'amount' => 400_000,
                'paid_amount' => 400_000,
                'status' => 'paid',
            ],
        ],
    ]);

    $rows = $plan->scheduleModalRows();
    $summaryCards = $plan->scheduleModalSummaryCards();
    $viewState = $plan->scheduleModalView();

    expect($plan->scheduleModalHasSchedule())->toBeTrue()
        ->and($viewState['has_schedule'])->toBeTrue()
        ->and($plan->scheduleModalTotalAmountLabel())->toBe('1.200.000đ')
        ->and($viewState['total_amount_label'])->toBe('1.200.000đ')
        ->and($plan->scheduleModalPaidProgressLabel())->toBe('1 / 3 kỳ')
        ->and($viewState['paid_progress_label'])->toBe('1 / 3 kỳ')
        ->and($plan->getCompletionPercentage())->toBe(33)
        ->and($rows)->toHaveCount(3)
        ->and($viewState['rows'])->toHaveCount(3)
        ->and($rows[0])->toMatchArray([
            'installment_label' => 'Kỳ 1',
            'due_date_label' => '01/04/2026',
            'amount_label' => '400.000đ',
            'status_label' => '⚠ Quá hạn',
        ])
        ->and($rows[0]['due_date_classes'])->toBe('text-red-600 font-semibold')
        ->and($rows[1]['show_near_due_notice'])->toBeTrue()
        ->and($rows[1]['status_label'])->toBe('○ Chờ thanh toán')
        ->and($rows[2]['status_label'])->toBe('✓ Đã thanh toán')
        ->and($summaryCards[0])->toMatchArray([
            'label' => 'Đã thanh toán',
            'value' => '500.000đ',
        ])
        ->and($summaryCards[1])->toMatchArray([
            'label' => 'Còn lại',
            'value' => '700.000đ',
        ])
        ->and($summaryCards[2])->toMatchArray([
            'label' => 'Tiến độ',
            'value' => '33%',
        ]);
});

it('renders installment schedule modal from installment plan presentation methods instead of inline blade php', function (): void {
    $blade = File::get(resource_path('views/filament/modals/installment-schedule.blade.php'));
    $shell = File::get(resource_path('views/filament/modals/partials/installment-schedule-shell.blade.php'));
    $table = File::get(resource_path('views/filament/modals/partials/installment-schedule-table.blade.php'));
    $summaryCards = File::get(resource_path('views/filament/modals/partials/installment-schedule-summary-cards.blade.php'));
    $emptyState = File::get(resource_path('views/filament/modals/partials/installment-schedule-empty-state.blade.php'));
    $model = File::get(app_path('Models/InstallmentPlan.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.modals.partials.installment-schedule-shell', ['viewState' => \$plan->scheduleModalView()])")
        ->and($shell)->toContain("\$viewState['has_schedule']")
        ->and($shell)->toContain("@include('filament.modals.partials.installment-schedule-table', ['viewState' => \$viewState])")
        ->and($shell)->toContain("@include('filament.modals.partials.installment-schedule-summary-cards', ['summaryCards' => \$viewState['summary_cards']])")
        ->and($shell)->toContain("@include('filament.modals.partials.installment-schedule-empty-state', ['emptyState' => \$viewState['empty_state']])")
        ->and($table)->toContain("\$viewState['rows'] as \$installment")
        ->and($table)->toContain("\$viewState['total_amount_label']")
        ->and($table)->toContain("\$viewState['paid_progress_label']")
        ->and($summaryCards)->toContain('@foreach($summaryCards as $summaryCard)')
        ->and($emptyState)->toContain("\$emptyState['title']")
        ->and($model)->toContain('public function scheduleModalView(): array')
        ->not->toContain('number_format($plan->total_amount')
        ->not->toContain('number_format($plan->paid_amount')
        ->not->toContain('count(array_filter($plan->schedule');
});
