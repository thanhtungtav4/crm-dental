<?php

namespace App\Filament\Pages\Reports;

use App\Models\TreatmentSession;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class FactoryStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê xưởng/labo';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'factory-statistical';

    protected function getDateColumn(): ?string
    {
        return 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        return TreatmentSession::query()
            ->with(['treatmentPlan.patient', 'planItem.service', 'doctor']);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('treatmentPlan.patient.full_name')
                ->label('Khách hàng')
                ->searchable()
                ->default('-'),
            TextColumn::make('planItem.service.name')
                ->label('Thủ thuật')
                ->default('-')
                ->wrap(),
            TextColumn::make('doctor.name')
                ->label('Bác sĩ')
                ->default('-'),
            TextColumn::make('performed_at')
                ->label('Ngày điều trị')
                ->dateTime('d/m/Y H:i'),
            TextColumn::make('status')
                ->label('Trạng thái')
                ->badge()
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'completed' => 'Hoàn thành',
                    'in_progress' => 'Đang thực hiện',
                    'pending' => 'Chờ xử lý',
                    default => 'Chưa xác định',
                }),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Khách hàng', 'value' => fn ($record) => $record->treatmentPlan?->patient?->full_name],
            ['label' => 'Thủ thuật', 'value' => fn ($record) => $record->planItem?->service?->name],
            ['label' => 'Bác sĩ', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Ngày điều trị', 'value' => fn ($record) => $record->performed_at],
            ['label' => 'Trạng thái', 'value' => fn ($record) => match ($record->status) {
                'completed' => 'Hoàn thành',
                'in_progress' => 'Đang thực hiện',
                'pending' => 'Chờ xử lý',
                default => 'Chưa xác định',
            }],
        ];
    }

    public function getStats(): array
    {
        $baseQuery = TreatmentSession::query();
        $this->applyDateRange($baseQuery, 'created_at');

        $total = (clone $baseQuery)->count();

        return [
            ['label' => 'Tổng phiên điều trị', 'value' => number_format($total)],
        ];
    }
}
