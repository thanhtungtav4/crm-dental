<?php

namespace App\Filament\Pages\Reports;

use App\Models\ReceiptExpense;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class RevenueExpenditure extends BaseReportPage
{
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
        return ReceiptExpense::query()
            ->selectRaw('payment_method, sum(case when voucher_type = \"expense\" then amount else 0 end) as total_expense, sum(case when voucher_type = \"expense\" then 0 else amount end) as total_receipt')
            ->groupBy('payment_method');
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
        $baseQuery = ReceiptExpense::query();
        $this->applyDateRange($baseQuery, 'voucher_date');

        $totalReceipt = (clone $baseQuery)->where('voucher_type', '!=', 'expense')->sum('amount');
        $totalExpense = (clone $baseQuery)->where('voucher_type', 'expense')->sum('amount');

        return [
            ['label' => 'Tổng thu', 'value' => number_format($totalReceipt) . ' đ'],
            ['label' => 'Tổng chi', 'value' => number_format($totalExpense) . ' đ'],
            ['label' => 'Biến động', 'value' => number_format($totalReceipt - $totalExpense) . ' đ'],
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
}
