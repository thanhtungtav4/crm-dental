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
                ->formatStateUsing(fn (?string $state) => $this->formatRiskLevel($state))
                ->color(fn (?string $state) => match ($state) {
                    PatientRiskProfile::LEVEL_HIGH => 'danger',
                    PatientRiskProfile::LEVEL_MEDIUM => 'warning',
                    default => 'success',
                }),
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
                ->options([
                    PatientRiskProfile::LEVEL_LOW => 'Thấp',
                    PatientRiskProfile::LEVEL_MEDIUM => 'Trung bình',
                    PatientRiskProfile::LEVEL_HIGH => 'Cao',
                ]),
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions())
                ->query(function (Builder $query, array $data): Builder {
                    if (! filled($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('patient', function (Builder $branchQuery) use ($data): void {
                        $branchQuery->where('first_branch_id', (int) $data['value']);
                    });
                }),
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
            ['label' => 'Mức độ risk', 'value' => fn ($record) => $this->formatRiskLevel($record->risk_level)],
            ['label' => 'Khuyến nghị', 'value' => fn ($record) => $record->recommended_action ?? ''],
            ['label' => 'Model version', 'value' => fn ($record) => $record->model_version],
        ];
    }

    public function getStats(): array
    {
        [$from, $until] = $this->getDateRangeFromFilters();
        $summary = $this->patientInsights()->riskSummary(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
            filled($this->getFilterValue('risk_level')) ? (string) $this->getFilterValue('risk_level') : null,
        );

        return [
            ['label' => 'Tổng profile', 'value' => number_format($summary['total'])],
            ['label' => 'Risk cao', 'value' => number_format($summary['high'])],
            ['label' => 'Risk trung bình', 'value' => number_format($summary['medium'])],
            ['label' => 'Risk thấp', 'value' => number_format($summary['low'])],
            ['label' => 'Avg no-show risk', 'value' => number_format($summary['average_no_show'], 2)],
            ['label' => 'Avg churn risk', 'value' => number_format($summary['average_churn'], 2)],
            ['label' => 'Ticket can thiệp đang mở', 'value' => number_format($summary['active_intervention_tickets'])],
        ];
    }

    protected function formatRiskLevel(?string $riskLevel): string
    {
        return match ($riskLevel) {
            PatientRiskProfile::LEVEL_HIGH => 'Cao',
            PatientRiskProfile::LEVEL_MEDIUM => 'Trung bình',
            PatientRiskProfile::LEVEL_LOW => 'Thấp',
            default => 'Không xác định',
        };
    }

    protected function patientInsights(): PatientInsightReportReadModelService
    {
        return app(PatientInsightReportReadModelService::class);
    }
}
