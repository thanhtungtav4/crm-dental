<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialDashboardReadModelService
{
    /**
     * @return array{
     *     today_revenue: float,
     *     yesterday_revenue: float,
     *     today_change: float,
     *     this_month_revenue: float,
     *     last_month_revenue: float,
     *     month_change: float,
     *     total_outstanding: float,
     *     overdue_count: int,
     *     last_7_days: array<int, float>
     * }
     */
    public function revenueOverview(?User $user = null): array
    {
        $todayRevenue = (float) $this->paymentQuery($user)
            ->today()
            ->sum('amount');
        $yesterdayRevenue = (float) $this->paymentQuery($user)
            ->whereDate('paid_at', Carbon::yesterday())
            ->sum('amount');
        $thisMonthRevenue = (float) $this->paymentQuery($user)
            ->thisMonth()
            ->sum('amount');

        $lastMonth = now()->subMonth();
        $lastMonthRevenue = (float) $this->paymentQuery($user)
            ->whereYear('paid_at', $lastMonth->year)
            ->whereMonth('paid_at', $lastMonth->month)
            ->sum('amount');

        $totalOutstanding = $this->sumInvoiceBalances(
            $this->invoiceQuery($user)
                ->whereIn('status', [
                    Invoice::STATUS_ISSUED,
                    Invoice::STATUS_PARTIAL,
                    Invoice::STATUS_OVERDUE,
                ])
                ->get()
        );

        $last7Days = collect(range(6, 0))
            ->map(fn (int $daysAgo): float => (float) $this->paymentQuery($user)
                ->whereDate('paid_at', Carbon::today()->subDays($daysAgo))
                ->sum('amount'))
            ->all();

        return [
            'today_revenue' => $todayRevenue,
            'yesterday_revenue' => $yesterdayRevenue,
            'today_change' => $this->percentageChange($todayRevenue, $yesterdayRevenue),
            'this_month_revenue' => $thisMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'month_change' => $this->percentageChange($thisMonthRevenue, $lastMonthRevenue),
            'total_outstanding' => $totalOutstanding,
            'overdue_count' => $this->invoiceQuery($user)->overdue()->count(),
            'last_7_days' => $last7Days,
        ];
    }

    /**
     * @return array{
     *     unpaid_count: int,
     *     unpaid_total: float,
     *     partial_count: int,
     *     partial_balance: float,
     *     overdue_count: int,
     *     overdue_balance: float,
     *     week_collections: float,
     *     week_payments_count: int
     * }
     */
    public function outstandingBalances(?User $user = null): array
    {
        return [
            'unpaid_count' => $this->invoiceQuery($user)->unpaid()->count(),
            'unpaid_total' => (float) $this->invoiceQuery($user)->unpaid()->sum('total_amount'),
            'partial_count' => $this->invoiceQuery($user)->partiallyPaid()->count(),
            'partial_balance' => $this->sumInvoiceBalances($this->invoiceQuery($user)->partiallyPaid()->get()),
            'overdue_count' => $this->invoiceQuery($user)->overdue()->count(),
            'overdue_balance' => $this->sumInvoiceBalances($this->invoiceQuery($user)->overdue()->get()),
            'week_collections' => (float) $this->paymentQuery($user)->thisWeek()->sum('amount'),
            'week_payments_count' => $this->paymentQuery($user)->thisWeek()->count(),
        ];
    }

    /**
     * @return array{
     *     total_revenue: float,
     *     total_payments: int,
     *     avg_payment: float,
     *     total_invoices: int,
     *     paid_invoices: int,
     *     paid_percentage: float,
     *     cash_payments: float,
     *     card_payments: float,
     *     transfer_payments: float,
     *     insurance_payments: float,
     *     non_cash_total: float,
     *     cash_percentage: float,
     *     avg_invoice: float,
     *     highest_invoice: float,
     *     this_month_payments: int,
     *     last_month_payments: int,
     *     frequency_change: float
     * }
     */
    public function quickStats(?User $user = null): array
    {
        $totalRevenue = (float) $this->paymentQuery($user)->sum('amount');
        $totalPayments = $this->paymentQuery($user)->count();
        $totalInvoices = $this->invoiceQuery($user)->count();
        $paidInvoices = $this->invoiceQuery($user)->fullyPaid()->count();
        $cashPayments = (float) $this->paymentQuery($user)->cash()->sum('amount');
        $cardPayments = (float) $this->paymentQuery($user)->card()->sum('amount');
        $transferPayments = (float) $this->paymentQuery($user)->transfer()->sum('amount');
        $insurancePayments = (float) $this->paymentQuery($user)->insuranceOnly()->sum('amount');
        $nonCashTotal = $cardPayments + $transferPayments;

        $lastMonth = now()->subMonth();
        $thisMonthPayments = $this->paymentQuery($user)->thisMonth()->count();
        $lastMonthPayments = $this->paymentQuery($user)
            ->whereYear('paid_at', $lastMonth->year)
            ->whereMonth('paid_at', $lastMonth->month)
            ->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_payments' => $totalPayments,
            'avg_payment' => $totalPayments > 0 ? round($totalRevenue / $totalPayments, 2) : 0.0,
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'paid_percentage' => $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0.0,
            'cash_payments' => $cashPayments,
            'card_payments' => $cardPayments,
            'transfer_payments' => $transferPayments,
            'insurance_payments' => $insurancePayments,
            'non_cash_total' => $nonCashTotal,
            'cash_percentage' => $totalRevenue > 0 ? round(($cashPayments / $totalRevenue) * 100, 1) : 0.0,
            'avg_invoice' => $totalInvoices > 0 ? round((float) $this->invoiceQuery($user)->avg('total_amount'), 2) : 0.0,
            'highest_invoice' => (float) ($this->invoiceQuery($user)->max('total_amount') ?? 0),
            'this_month_payments' => $thisMonthPayments,
            'last_month_payments' => $lastMonthPayments,
            'frequency_change' => $this->percentageChange($thisMonthPayments, $lastMonthPayments),
        ];
    }

    /**
     * @return array{
     *     revenue: array<int, float>,
     *     count: array<int, int>,
     *     labels: array<int, string>
     * }
     */
    public function monthlyRevenueSeries(string $filter = 'year', ?User $user = null): array
    {
        $months = match ($filter) {
            '3months' => 3,
            '6months' => 6,
            default => 12,
        };

        $revenue = [];
        $count = [];
        $labels = [];

        for ($index = $months - 1; $index >= 0; $index--) {
            $date = Carbon::now()->subMonths($index);

            $revenue[] = (float) $this->paymentQuery($user)
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->sum('amount');

            $count[] = $this->paymentQuery($user)
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->count();

            $labels[] = $date->format('m/Y');
        }

        return [
            'revenue' => $revenue,
            'count' => $count,
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function paymentMethodTotals(string $filter = 'month', ?User $user = null): array
    {
        $query = $this->paymentQuery($user);

        match ($filter) {
            'today' => $query->today(),
            'week' => $query->thisWeek(),
            'year' => $query->whereYear('paid_at', now()->year),
            default => $query->thisMonth(),
        };

        return $query
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->mapWithKeys(fn (mixed $total, mixed $method): array => [(string) $method => (float) $total])
            ->all();
    }

    public function overdueInvoices(?User $user = null, int $limit = 10): Builder
    {
        $query = $this->invoiceQuery($user)
            ->overdue()
            ->with(['patient', 'plan'])
            ->orderBy('due_date', 'asc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @return array{
     *     today_revenue: float,
     *     today_change: float,
     *     last_7_days: array<int, float>,
     *     method_total: float,
     *     cash_payments: float,
     *     card_payments: float,
     *     transfer_payments: float,
     *     insurance_payments: float,
     *     unpaid_count: int,
     *     unpaid_total: float,
     *     overdue_count: int
     * }
     */
    public function paymentStatsSnapshot(?User $user = null): array
    {
        $overview = $this->revenueOverview($user);
        $quickStats = $this->quickStats($user);
        $balances = $this->outstandingBalances($user);

        return [
            'today_revenue' => $overview['today_revenue'],
            'today_change' => $overview['today_change'],
            'last_7_days' => $overview['last_7_days'],
            'method_total' => $quickStats['cash_payments'] + $quickStats['card_payments'] + $quickStats['transfer_payments'],
            'cash_payments' => $quickStats['cash_payments'],
            'card_payments' => $quickStats['card_payments'],
            'transfer_payments' => $quickStats['transfer_payments'],
            'insurance_payments' => $quickStats['insurance_payments'],
            'unpaid_count' => $balances['unpaid_count'],
            'unpaid_total' => $balances['unpaid_total'],
            'overdue_count' => $balances['overdue_count'],
        ];
    }

    /**
     * @return array{
     *     values: array<int, float|int>,
     *     labels: array<int, string>,
     *     background_color: array<int, string>,
     *     border_color: array<int, string>
     * }
     */
    public function paymentMethodChart(string $filter = 'month', ?User $user = null): array
    {
        $methods = ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true);
        $totals = $this->paymentMethodTotals($filter, $user);

        $values = [];
        $labels = [];
        $backgroundColor = [];
        $borderColor = [];
        $colorMap = [
            'cash' => ['rgba(34, 197, 94, 0.8)', 'rgb(34, 197, 94)'],
            'card' => ['rgba(59, 130, 246, 0.8)', 'rgb(59, 130, 246)'],
            'transfer' => ['rgba(251, 146, 60, 0.8)', 'rgb(251, 146, 60)'],
            'vnpay' => ['rgba(99, 102, 241, 0.8)', 'rgb(99, 102, 241)'],
            'other' => ['rgba(156, 163, 175, 0.8)', 'rgb(156, 163, 175)'],
        ];

        foreach ($methods as $method => $label) {
            $amount = $totals[$method] ?? 0;

            if ($amount <= 0) {
                continue;
            }

            $values[] = $amount;
            $labels[] = $label;
            $backgroundColor[] = $colorMap[$method][0] ?? 'rgba(156, 163, 175, 0.8)';
            $borderColor[] = $colorMap[$method][1] ?? 'rgb(156, 163, 175)';
        }

        if ($values === []) {
            $values = [0];
            $labels = ['Chưa có dữ liệu'];
            $backgroundColor = ['rgba(156, 163, 175, 0.8)'];
            $borderColor = ['rgb(156, 163, 175)'];
        }

        return [
            'values' => $values,
            'labels' => $labels,
            'background_color' => $backgroundColor,
            'border_color' => $borderColor,
        ];
    }

    /**
     * @return array{
     *     datasets: array<int, array{
     *         label: string,
     *         data: array<int, float|int>,
     *         borderColor: string,
     *         backgroundColor: string,
     *         fill: bool,
     *         tension: float,
     *         yAxisID?: string
     *     }>,
     *     labels: array<int, string>
     * }
     */
    public function monthlyRevenueChart(string $filter = 'year', ?User $user = null): array
    {
        $data = $this->monthlyRevenueSeries($filter, $user);

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

    /**
     * @return array{
     *     today: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         chart: array<int, float>,
     *         title: string
     *     },
     *     month: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string
     *     },
     *     outstanding: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string,
     *         url: string
     *     }
     * }
     */
    public function revenueOverviewCards(?User $user = null): array
    {
        $overview = $this->revenueOverview($user);
        $todayChange = $overview['today_change'];
        $monthChange = $overview['month_change'];
        $overdueCount = $overview['overdue_count'];

        return [
            'today' => [
                'label' => 'Doanh thu hôm nay',
                'value' => number_format($overview['today_revenue'], 0, ',', '.').'đ',
                'description' => $todayChange !== 0.0 ? abs($todayChange).'% so với hôm qua' : 'Không có thay đổi',
                'description_icon' => $todayChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
                'color' => $todayChange >= 0 ? 'success' : 'danger',
                'chart' => $overview['last_7_days'],
                'title' => 'Tổng doanh thu từ các khoản thanh toán hôm nay',
            ],
            'month' => [
                'label' => 'Doanh thu tháng này',
                'value' => number_format($overview['this_month_revenue'], 0, ',', '.').'đ',
                'description' => $monthChange !== 0.0 ? abs($monthChange).'% so với tháng trước' : 'Không có thay đổi',
                'description_icon' => $monthChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
                'color' => $monthChange >= 0 ? 'success' : 'danger',
                'title' => 'Tổng doanh thu tháng '.now()->format('m/Y'),
            ],
            'outstanding' => [
                'label' => 'Tổng công nợ',
                'value' => number_format($overview['total_outstanding'], 0, ',', '.').'đ',
                'description' => $overdueCount > 0 ? "{$overdueCount} hóa đơn quá hạn" : 'Không có quá hạn',
                'description_icon' => $overdueCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle,
                'color' => $overdueCount > 0 ? 'danger' : 'success',
                'title' => 'Tổng số tiền chưa thu được từ các hóa đơn',
                'url' => route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue', 'partial']]],
                ]),
            ],
        ];
    }

    /**
     * @return array{
     *     total_revenue: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string
     *     },
     *     payment_rate: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         chart: array<int, float>,
     *         title: string
     *     },
     *     cash_mix: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string
     *     },
     *     invoice_average: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string
     *     },
     *     payment_frequency: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string
     *     }
     * }
     */
    public function quickFinancialStatCards(?User $user = null): array
    {
        $stats = $this->quickStats($user);
        $paidPercentage = $stats['paid_percentage'];
        $frequencyChange = $stats['frequency_change'];

        return [
            'total_revenue' => [
                'label' => 'Tổng doanh thu',
                'value' => number_format($stats['total_revenue'], 0, ',', '.').'đ',
                'description' => $stats['total_payments'].' giao dịch | TB: '.number_format($stats['avg_payment'], 0, ',', '.').'đ',
                'description_icon' => Heroicon::OutlinedCurrencyDollar,
                'color' => 'success',
                'title' => 'Tổng doanh thu từ tất cả các khoản thanh toán',
            ],
            'payment_rate' => [
                'label' => 'Tỷ lệ thanh toán',
                'value' => $paidPercentage.'%',
                'description' => "{$stats['paid_invoices']}/{$stats['total_invoices']} hóa đơn đã thanh toán đầy đủ",
                'description_icon' => Heroicon::OutlinedCheckCircle,
                'color' => $paidPercentage >= 70 ? 'success' : ($paidPercentage >= 50 ? 'warning' : 'danger'),
                'chart' => [$paidPercentage, 100 - $paidPercentage],
                'title' => 'Phần trăm hóa đơn đã thanh toán hoàn tất',
            ],
            'cash_mix' => [
                'label' => 'Tiền mặt / Phi tiền mặt',
                'value' => number_format($stats['cash_payments'], 0, ',', '.').'đ',
                'description' => 'Phi tiền mặt: '.number_format($stats['non_cash_total'], 0, ',', '.').'đ ('.(100 - $stats['cash_percentage']).'%)',
                'description_icon' => Heroicon::OutlinedCreditCard,
                'color' => 'info',
                'title' => 'So sánh thanh toán tiền mặt và phi tiền mặt',
            ],
            'invoice_average' => [
                'label' => 'Giá trị HĐ trung bình',
                'value' => number_format($stats['avg_invoice'], 0, ',', '.').'đ',
                'description' => 'Cao nhất: '.number_format($stats['highest_invoice'], 0, ',', '.').'đ',
                'description_icon' => Heroicon::OutlinedDocumentText,
                'color' => 'info',
                'title' => 'Giá trị trung bình của các hóa đơn',
            ],
            'payment_frequency' => [
                'label' => 'Tần suất thanh toán',
                'value' => $stats['this_month_payments'].' thanh toán/tháng',
                'description' => $frequencyChange > 0
                    ? "+{$frequencyChange}% so với tháng trước"
                    : ($frequencyChange < 0 ? "{$frequencyChange}% so với tháng trước" : 'Không đổi'),
                'description_icon' => $frequencyChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
                'color' => $frequencyChange >= 0 ? 'success' : 'danger',
                'title' => 'Số lượng thanh toán trung bình mỗi tháng',
            ],
        ];
    }

    /**
     * @return array{
     *     unpaid: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string,
     *         url: string
     *     },
     *     partial: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string,
     *         url: string
     *     },
     *     overdue: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string,
     *         url: string
     *     },
     *     week: array{
     *         label: string,
     *         value: string,
     *         description: string,
     *         description_icon: string,
     *         color: string,
     *         title: string,
     *         url: string
     *     }
     * }
     */
    public function outstandingBalanceCards(?User $user = null): array
    {
        $balances = $this->outstandingBalances($user);

        return [
            'unpaid' => [
                'label' => 'Hóa đơn chưa thanh toán',
                'value' => $balances['unpaid_count'].' hóa đơn',
                'description' => 'Tổng: '.number_format($balances['unpaid_total'], 0, ',', '.').'đ',
                'description_icon' => Heroicon::OutlinedDocumentText,
                'color' => 'warning',
                'title' => 'Hóa đơn chưa có khoản thanh toán nào',
                'url' => route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['payment_progress' => ['value' => 'unpaid']],
                ]),
            ],
            'partial' => [
                'label' => 'Thanh toán một phần',
                'value' => $balances['partial_count'].' hóa đơn',
                'description' => 'Còn lại: '.number_format($balances['partial_balance'], 0, ',', '.').'đ',
                'description_icon' => Heroicon::OutlinedClock,
                'color' => 'info',
                'title' => 'Hóa đơn đã thanh toán một phần',
                'url' => route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['partial']]],
                ]),
            ],
            'overdue' => [
                'label' => 'Hóa đơn quá hạn',
                'value' => $balances['overdue_count'].' hóa đơn',
                'description' => 'Nợ: '.number_format($balances['overdue_balance'], 0, ',', '.').'đ',
                'description_icon' => Heroicon::OutlinedExclamationTriangle,
                'color' => 'danger',
                'title' => 'Hóa đơn đã quá ngày đến hạn',
                'url' => route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue']]],
                ]),
            ],
            'week' => [
                'label' => 'Thu tuần này',
                'value' => number_format($balances['week_collections'], 0, ',', '.').'đ',
                'description' => $balances['week_payments_count'].' giao dịch',
                'description_icon' => Heroicon::OutlinedBanknotes,
                'color' => 'success',
                'title' => 'Tổng thu từ đầu tuần đến nay',
                'url' => route('filament.admin.resources.payments.index'),
            ],
        ];
    }

    protected function invoiceQuery(?User $user = null): Builder
    {
        return $this->scopeQueryToAccessibleBranches(Invoice::query(), $user);
    }

    protected function paymentQuery(?User $user = null): Builder
    {
        return $this->scopeQueryToAccessibleBranches(Payment::query(), $user);
    }

    protected function scopeQueryToAccessibleBranches(
        Builder $query,
        ?User $user = null,
        string $column = 'branch_id'
    ): Builder {
        $dashboardUser = $user ?? auth()->user();

        if (! $dashboardUser instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($dashboardUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($dashboardUser, true);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    protected function sumInvoiceBalances(Collection $invoices): float
    {
        return round($invoices->sum(
            fn (Invoice $invoice): float => $invoice->calculateBalance()
        ), 2);
    }

    protected function percentageChange(float|int $current, float|int $previous): float
    {
        if ($previous <= 0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
