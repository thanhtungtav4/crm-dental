<?php

namespace App\Filament\Pages\Reports;

use App\Models\ReceiptExpense;
use App\Services\FinancialReportReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RevenueExpenditure extends BaseReportPage
{
    protected ?bool $hasReceiptsExpenseTable = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dòng tiền (thu/chi)';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'revenue-expenditure';

    protected function getDateColumn(): ?string
    {
        return 'voucher_date';
    }

    protected function getTableQuery(): Builder
    {
        if (! $this->hasReceiptsExpenseTable()) {
            return ReceiptExpense::query()->whereRaw('1 = 0');
        }

        return $this->financialReports()
            ->cashflowBreakdownQuery($this->resolvedVisibleBranchIds());
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions()),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('payment_method')
                ->label('Phương thức thanh toán')
                ->formatStateUsing(fn (?string $state) => $this->formatPaymentMethod($state))
                ->badge(),
            TextColumn::make('total_receipt')
                ->label('Phát sinh tăng')
                ->money('VND', true),
            TextColumn::make('total_expense')
                ->label('Phát sinh giảm')
                ->money('VND', true),
            TextColumn::make('net_change')
                ->label('Biến động số dư')
                ->getStateUsing(fn ($record) => ($record->total_receipt ?? 0) - ($record->total_expense ?? 0))
                ->money('VND', true),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Phương thức thanh toán', 'value' => fn ($record) => $this->formatPaymentMethod($record->payment_method)],
            ['label' => 'Phát sinh tăng', 'value' => fn ($record) => $record->total_receipt],
            ['label' => 'Phát sinh giảm', 'value' => fn ($record) => $record->total_expense],
            ['label' => 'Biến động số dư', 'value' => fn ($record) => ($record->total_receipt ?? 0) - ($record->total_expense ?? 0)],
        ];
    }

    public function getStats(): array
    {
        if (! $this->hasReceiptsExpenseTable()) {
            return [
                ['label' => 'Tổng thu', 'value' => '0 đ'],
                ['label' => 'Tổng chi', 'value' => '0 đ'],
                ['label' => 'Biến động', 'value' => '0 đ'],
            ];
        }

        [$from, $until] = $this->getDateRangeFromFilters();
        $summary = $this->financialReports()->cashflowSummary(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
        );
        $totalReceipt = $summary['total_receipt'];
        $totalExpense = $summary['total_expense'];

        return [
            ['label' => 'Tổng thu', 'value' => number_format($totalReceipt).' đ'],
            ['label' => 'Tổng chi', 'value' => number_format($totalExpense).' đ'],
            ['label' => 'Biến động', 'value' => number_format($totalReceipt - $totalExpense).' đ'],
        ];
    }

    protected function formatPaymentMethod(?string $state): string
    {
        return match ($state) {
            'cash' => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'card' => 'Thẻ',
            default => 'Khác',
        };
    }

    protected function hasReceiptsExpenseTable(): bool
    {
        if ($this->hasReceiptsExpenseTable !== null) {
            return $this->hasReceiptsExpenseTable;
        }

        return $this->hasReceiptsExpenseTable = Schema::hasTable('receipts_expense');
    }

    protected function financialReports(): FinancialReportReadModelService
    {
        return app(FinancialReportReadModelService::class);
    }
}
