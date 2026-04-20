<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChartWidget extends ChartWidget
{
    use InteractsWithFinancialBranchScope;

    protected ?string $heading = 'Phân tích phương thức thanh toán';

    protected static ?int $sort = 4;

    public ?string $filter = 'month';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = app(FinancialDashboardReadModelService::class)
            ->paymentMethodChart($this->filter ?? 'month', auth()->user());

        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu theo phương thức',
                    'data' => $data['values'],
                    'backgroundColor' => $data['background_color'],
                    'borderColor' => $data['border_color'],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'callbacks' => [
                        'label' => 'function(context) {
                            let label = context.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat("vi-VN").format(context.parsed) + "đ";
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += " (" + percentage + "%)";
                            }
                            return label;
                        }',
                    ],
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hôm nay',
            'week' => 'Tuần này',
            'month' => 'Tháng này',
            'year' => 'Năm nay',
        ];
    }
}
