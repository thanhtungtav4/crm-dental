<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\Note;
use App\Models\PatientRiskProfile;
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
        return PatientRiskProfile::query()
            ->with(['patient.branch']);
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
                ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
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
        $query = $this->buildFilteredRiskQuery();
        $total = (clone $query)->count();
        $high = (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_HIGH)->count();
        $medium = (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_MEDIUM)->count();
        $low = (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_LOW)->count();
        $averageNoShow = round((float) (clone $query)->avg('no_show_risk_score'), 2);
        $averageChurn = round((float) (clone $query)->avg('churn_risk_score'), 2);

        $activeInterventionTickets = Note::query()
            ->where('care_type', 'risk_high_follow_up')
            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
            ->when(filled($this->getFilterValue('branch_id')), function (Builder $noteQuery): void {
                $noteQuery->whereHas('patient', function (Builder $patientQuery): void {
                    $patientQuery->where('first_branch_id', (int) $this->getFilterValue('branch_id'));
                });
            })
            ->count();

        return [
            ['label' => 'Tổng profile', 'value' => number_format($total)],
            ['label' => 'Risk cao', 'value' => number_format($high)],
            ['label' => 'Risk trung bình', 'value' => number_format($medium)],
            ['label' => 'Risk thấp', 'value' => number_format($low)],
            ['label' => 'Avg no-show risk', 'value' => number_format($averageNoShow, 2)],
            ['label' => 'Avg churn risk', 'value' => number_format($averageChurn, 2)],
            ['label' => 'Ticket can thiệp đang mở', 'value' => number_format($activeInterventionTickets)],
        ];
    }

    protected function buildFilteredRiskQuery(): Builder
    {
        $query = PatientRiskProfile::query();
        $this->applyDateRange($query, 'as_of_date');

        $riskLevel = $this->getFilterValue('risk_level');
        if (filled($riskLevel)) {
            $query->where('risk_level', $riskLevel);
        }

        $branchId = $this->getFilterValue('branch_id');
        if (filled($branchId)) {
            $query->whereHas('patient', function (Builder $patientQuery) use ($branchId): void {
                $patientQuery->where('first_branch_id', (int) $branchId);
            });
        }

        return $query;
    }

    protected function getFilterValue(string $filterName): mixed
    {
        return data_get($this->tableFilters ?? [], "{$filterName}.value");
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
}
