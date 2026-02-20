<?php

namespace App\Filament\Pages\Reports;

use App\Models\Patient;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class PatientStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê khách hàng';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'patient-statistical';

    protected function getDateColumn(): ?string
    {
        return 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        return Patient::query()
            ->selectRaw('primary_doctor_id, count(*) as total_patients')
            ->with('primaryDoctor')
            ->groupBy('primary_doctor_id');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('primaryDoctor.name')
                ->label('Bác sĩ')
                ->default('Chưa phân công')
                ->sortable(),
            TextColumn::make('total_patients')
                ->label('Số khách hàng')
                ->numeric()
                ->sortable(),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Bác sĩ', 'value' => fn ($record) => $record->primaryDoctor?->name ?? 'Chưa phân công'],
            ['label' => 'Số khách hàng', 'value' => fn ($record) => $record->total_patients],
        ];
    }

    public function getStats(): array
    {
        $baseQuery = Patient::query();
        $this->applyDateRange($baseQuery, 'created_at');

        $totalPatients = (clone $baseQuery)->count();

        return [
            ['label' => 'Tổng khách hàng', 'value' => number_format($totalPatients)],
        ];
    }
}
