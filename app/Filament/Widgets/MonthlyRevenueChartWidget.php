<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Doanh thu 12 tháng';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected ?string $maxHeight = '300px';
    
    public ?string $filter = 'year';

    protected function getData(): array
    {
        $data = $this->getRevenueData();
        
        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu (VNĐ)',
                    'data' => $data['revenue'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Số lượng thanh toán',
                    'data' => $data['count'],
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 0) {
                                    label += new Intl.NumberFormat("vi-VN").format(context.parsed.y) + "đ";
                                } else {
                                    label += context.parsed.y + " thanh toán";
                                }
                            }
                            return label;
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                    'ticks' => [
                        'callback' => 'function(value) {
                            return new Intl.NumberFormat("vi-VN", {
                                notation: "compact",
                                compactDisplay: "short"
                            }).format(value) + "đ";
                        }',
                    ],
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
    
    protected function getFilters(): ?array
    {
        return [
            'year' => 'Năm nay (12 tháng)',
            '6months' => '6 tháng gần đây',
            '3months' => '3 tháng gần đây',
        ];
    }
    
    private function getRevenueData(): array
    {
        $months = match($this->filter) {
            '3months' => 3,
            '6months' => 6,
            default => 12,
        };
        
        $revenue = [];
        $count = [];
        $labels = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            
            $monthRevenue = Payment::query()
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->sum('amount');
            
            $monthCount = Payment::query()
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->count();
            
            $revenue[] = $monthRevenue;
            $count[] = $monthCount;
            $labels[] = $date->format('m/Y');
        }
        
        return [
            'revenue' => $revenue,
            'count' => $count,
            'labels' => $labels,
        ];
    }
}
