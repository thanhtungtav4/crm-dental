<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\Carbon;
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
