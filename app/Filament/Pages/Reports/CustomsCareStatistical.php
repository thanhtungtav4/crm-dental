<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\Note;
use App\Models\ReportCareQueueDailyAggregate;
use App\Support\ClinicRuntimeSettings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class CustomsCareStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê CSKH';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'customs-care-statistical';

    protected ?bool $usesCareAggregateCache = null;

    protected function getDateColumn(): ?string
    {
        return $this->usesCareAggregate()
            ? 'snapshot_date'
            : 'care_at';
    }

    protected function getTableQuery(): Builder
    {
        if ($this->usesCareAggregate()) {
            $scopeId = $this->selectedBranchScopeId();

            return ReportCareQueueDailyAggregate::query()
                ->from('report_care_queue_daily_aggregates as care_daily')
                ->selectRaw('
                    care_daily.care_type as care_type,
                    MAX(care_daily.care_type_label) as care_type_label,
                    care_daily.care_status as care_status,
                    MAX(care_daily.care_status_label) as care_status_label,
                    SUM(care_daily.total_count) as total_count,
                    MAX(care_daily.latest_care_at) as care_at,
                    MAX(care_daily.snapshot_date) as snapshot_date
                ')
                ->where('care_daily.branch_scope_id', $scopeId)
                ->groupBy('care_daily.care_type', 'care_daily.care_status');
        }

        return Note::query()
            ->selectRaw('care_type, care_status, count(*) as total_count, max(care_at) as care_at')
            ->whereNotNull('care_type')
            ->whereNotNull('care_status')
            ->when(
                filled($this->getFilterValue('branch_id')),
                function (Builder $query): void {
                    $branchId = (int) $this->getFilterValue('branch_id');

                    $query->where(function (Builder $scopeQuery) use ($branchId): void {
                        $scopeQuery->where('branch_id', $branchId)
                            ->orWhere(function (Builder $legacyQuery) use ($branchId): void {
                                $legacyQuery->whereNull('branch_id')
                                    ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->where('first_branch_id', $branchId));
                            });
                    });
                }
            )
            ->groupBy('care_type', 'care_status');
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all()),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('care_type')
                ->label('Phân loại')
                ->formatStateUsing(function (?string $state, $record): string {
                    if ($this->usesCareAggregate()) {
                        return (string) ($record->care_type_label ?? 'Chăm sóc chung');
                    }

                    return $this->getCareTypeOptions()[$state] ?? 'Chăm sóc chung';
                })
                ->badge(),
            TextColumn::make('care_status')
                ->label('Trạng thái')
                ->formatStateUsing(function (?string $state, $record): string {
                    if ($this->usesCareAggregate()) {
                        return (string) ($record->care_status_label ?? Note::careStatusLabel($state));
                    }

                    return Note::careStatusLabel($state);
                })
                ->color(fn (?string $state) => Note::careStatusColor($state))
                ->badge(),
            TextColumn::make('total_count')
                ->label('Số lượng')
                ->numeric(),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            [
                'label' => 'Phân loại',
                'value' => fn ($record) => $this->usesCareAggregate()
                    ? $record->care_type_label
                    : ($this->getCareTypeOptions()[$record->care_type] ?? 'Chăm sóc chung'),
            ],
            [
                'label' => 'Trạng thái',
                'value' => fn ($record) => $this->usesCareAggregate()
                    ? $record->care_status_label
                    : Note::careStatusLabel($record->care_status),
            ],
            ['label' => 'Số lượng', 'value' => fn ($record) => $record->total_count],
        ];
    }

    public function getStats(): array
    {
        if ($this->usesCareAggregate()) {
            $baseQuery = ReportCareQueueDailyAggregate::query();
            $this->applyDateRange($baseQuery, 'snapshot_date');
            $baseQuery->where('branch_scope_id', $this->selectedBranchScopeId());

            $total = (int) (clone $baseQuery)->sum('total_count');
            $completed = (int) (clone $baseQuery)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
                ->sum('total_count');
            $planned = (int) (clone $baseQuery)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))
                ->sum('total_count');
        } else {
            $baseQuery = Note::query();
            $this->applyDateRange($baseQuery, 'care_at');
            $branchId = $this->getFilterValue('branch_id');

            if (filled($branchId)) {
                $baseQuery->where(function (Builder $scopeQuery) use ($branchId): void {
                    $scopeQuery->where('branch_id', (int) $branchId)
                        ->orWhere(function (Builder $legacyQuery) use ($branchId): void {
                            $legacyQuery->whereNull('branch_id')
                                ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->where('first_branch_id', (int) $branchId));
                        });
                });
            }

            $total = (clone $baseQuery)->count();
            $completed = (clone $baseQuery)->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))->count();
            $planned = (clone $baseQuery)->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))->count();
        }

        return [
            ['label' => 'Tổng chăm sóc', 'value' => number_format($total)],
            ['label' => 'Hoàn thành', 'value' => number_format($completed)],
            ['label' => 'Đã đặt lịch', 'value' => number_format($planned)],
        ];
    }

    protected function getCareTypeOptions(): array
    {
        return ClinicRuntimeSettings::careTypeDisplayOptions();
    }

    protected function getCareStatusOptions(): array
    {
        return Note::careStatusOptions();
    }

    protected function getFilterValue(string $filterName): mixed
    {
        return data_get($this->tableFilters ?? [], "{$filterName}.value");
    }

    protected function usesCareAggregate(): bool
    {
        if ($this->usesCareAggregateCache !== null) {
            return $this->usesCareAggregateCache;
        }

        $this->usesCareAggregateCache = ReportCareQueueDailyAggregate::query()->exists();

        return $this->usesCareAggregateCache;
    }

    protected function selectedBranchScopeId(): int
    {
        $branchId = $this->getFilterValue('branch_id');

        return filled($branchId)
            ? (int) $branchId
            : 0;
    }
}
