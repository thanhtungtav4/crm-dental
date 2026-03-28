<?php

namespace App\Filament\Pages\Reports;

use App\Services\PatientInsightReportReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
        return $this->patientInsights()
            ->patientBreakdownQuery($this->resolvedVisibleBranchIds());
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
        [$from, $until] = $this->getDateRangeFromFilters();
        $summary = $this->patientInsights()->patientSummary(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
        );

        return [
            ['label' => 'Tổng khách hàng', 'value' => number_format($summary['total_patients'])],
        ];
    }

    protected function patientInsights(): PatientInsightReportReadModelService
    {
        return app(PatientInsightReportReadModelService::class);
    }
}
