<?php

namespace App\Filament\Pages\Reports;

use App\Models\PatientRiskProfile;
use App\Services\PatientInsightReportReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class RiskScoringDashboard extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Risk no-show/churn';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'risk-scoring-dashboard';

    protected function getDateColumn(): ?string
    {
        return 'as_of_date';
    }

    protected function getTableQuery(): Builder
    {
        return $this->patientInsights()
            ->riskProfileQuery($this->resolvedVisibleBranchIds());
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('as_of_date')
                ->label('Ngày score')
                ->date('d/m/Y')
                ->sortable(),
            TextColumn::make('patient.patient_code')
                ->label('Mã bệnh nhân')
                ->searchable(),
            TextColumn::make('patient.full_name')
                ->label('Bệnh nhân')
                ->searchable(),
            TextColumn::make('patient.branch.name')
                ->label('Chi nhánh')
                ->placeholder('-')
                ->searchable(),
            TextColumn::make('no_show_risk_score')
                ->label('No-show risk')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            TextColumn::make('churn_risk_score')
                ->label('Churn risk')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            TextColumn::make('risk_level')
                ->label('Mức độ')
                ->badge()
                ->formatStateUsing(fn (?string $state) => PatientRiskProfile::levelLabel($state))
                ->color(fn (?string $state) => PatientRiskProfile::levelColor($state)),
            TextColumn::make('recommended_action')
                ->label('Khuyến nghị')
                ->limit(70)
                ->tooltip(fn (PatientRiskProfile $record) => $record->recommended_action),
            TextColumn::make('generated_at')
                ->label('Generated at')
                ->dateTime('d/m/Y H:i')
                ->placeholder('-')
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('risk_level')
                ->label('Mức độ risk')
                ->options(PatientRiskProfile::levelOptions()),
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions())
                ->query(fn (Builder $query): Builder => $query),
        ]);
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Ngày score', 'value' => fn ($record) => $record->as_of_date?->format('Y-m-d')],
            ['label' => 'Mã bệnh nhân', 'value' => fn ($record) => $record->patient?->patient_code ?? ''],
            ['label' => 'Bệnh nhân', 'value' => fn ($record) => $record->patient?->full_name ?? ''],
            ['label' => 'Chi nhánh', 'value' => fn ($record) => $record->patient?->branch?->name ?? ''],
            ['label' => 'No-show risk', 'value' => fn ($record) => (float) $record->no_show_risk_score],
            ['label' => 'Churn risk', 'value' => fn ($record) => (float) $record->churn_risk_score],
            ['label' => 'Mức độ risk', 'value' => fn ($record) => PatientRiskProfile::levelLabel($record->risk_level)],
            ['label' => 'Khuyến nghị', 'value' => fn ($record) => $record->recommended_action ?? ''],
            ['label' => 'Model version', 'value' => fn ($record) => $record->model_version],
        ];
    }

    public function getStats(): array
    {
        [$from, $until] = $this->getDateRangeFromFilters();

        return $this->patientInsights()->riskSummaryStatsPayload(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
            filled($this->getFilterValue('risk_level')) ? (string) $this->getFilterValue('risk_level') : null,
        );
    }

    protected function patientInsights(): PatientInsightReportReadModelService
    {
        return app(PatientInsightReportReadModelService::class);
    }
}
