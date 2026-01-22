<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Phân tích phương thức thanh toán';
    
    protected static ?int $sort = 4;
    
    public ?string $filter = 'month';
    
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = $this->getMethodData();
        
        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu theo phương thức',
                    'data' => $data['values'],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Green for cash
                        'rgba(59, 130, 246, 0.8)',   // Blue for card
                        'rgba(251, 146, 60, 0.8)',   // Orange for transfer
                        'rgba(156, 163, 175, 0.8)',  // Gray for other
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(59, 130, 246)',
                        'rgb(251, 146, 60)',
                        'rgb(156, 163, 175)',
                    ],
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
    
    private function getMethodData(): array
    {
        $query = Payment::query();
        
        match($this->filter) {
            'today' => $query->today(),
            'week' => $query->thisWeek(),
            'month' => $query->thisMonth(),
            'year' => $query->whereYear('paid_at', now()->year),
            default => $query->thisMonth(),
        };
        
        $methods = [
            'cash' => '💵 Tiền mặt',
            'card' => '💳 Thẻ',
            'transfer' => '🏦 Chuyển khoản',
            'other' => '📝 Khác',
        ];
        
        $values = [];
        $labels = [];
        
        foreach ($methods as $method => $label) {
            $amount = (clone $query)->where('method', $method)->sum('amount');
            if ($amount > 0) {
                $values[] = $amount;
                $labels[] = $label;
            }
        }
        
        // If no data, show empty state
        if (empty($values)) {
            $values = [0];
            $labels = ['Chưa có dữ liệu'];
        }
        
        return [
            'values' => $values,
            'labels' => $labels,
        ];
    }
}
