<?php

namespace App\Filament\Pages\Reports;

use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class OwedStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Công nợ';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'owed-statistical';

    protected function getDateColumn(): ?string
    {
        return 'issued_at';
    }

    protected function getTableQuery(): Builder
    {
        return Invoice::query()->with(['patient']);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ và tên')
                ->searchable(),
            TextColumn::make('patient.phone')
                ->label('Số điện thoại')
                ->searchable(),
            TextColumn::make('total_amount')
                ->label('Phải thanh toán')
                ->money('VND', true)
                ->sortable(),
            TextColumn::make('paid_amount')
                ->label('Đã thanh toán')
                ->money('VND', true),
            TextColumn::make('balance')
                ->label('Công nợ')
                ->getStateUsing(fn ($record) => $record->calculateBalance())
                ->money('VND', true),
            TextColumn::make('issued_at')
                ->label('Ngày thanh toán gần nhất')
                ->dateTime('d/m/Y H:i'),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ và tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Số điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Phải thanh toán', 'value' => fn ($record) => $record->total_amount],
            ['label' => 'Đã thanh toán', 'value' => fn ($record) => $record->paid_amount],
            ['label' => 'Công nợ', 'value' => fn ($record) => $record->calculateBalance()],
            ['label' => 'Ngày thanh toán gần nhất', 'value' => fn ($record) => $record->issued_at],
        ];
    }

    public function getStats(): array
    {
        $baseQuery = Invoice::query();
        $this->applyDateRange($baseQuery, 'issued_at');

        $totalAmount = (clone $baseQuery)->sum('total_amount');
        $paidAmount = (clone $baseQuery)->sum('paid_amount');
        $balance = $totalAmount - $paidAmount;

        return [
            ['label' => 'Tổng phải thanh toán', 'value' => number_format($totalAmount) . ' đ'],
            ['label' => 'Đã thanh toán', 'value' => number_format($paidAmount) . ' đ'],
            ['label' => 'Công nợ', 'value' => number_format($balance) . ' đ'],
        ];
    }
}
