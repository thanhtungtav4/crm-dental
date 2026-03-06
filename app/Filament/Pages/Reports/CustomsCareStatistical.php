<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\Note;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\User;
use App\Support\BranchAccess;
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

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('ViewAny:Note')
            && $authUser->hasAnyAccessibleBranch();
    }

    protected function getDateColumn(): ?string
    {
        return $this->usesCareAggregate()
            ? 'snapshot_date'
            : 'care_at';
    }

    protected function getTableQuery(): Builder
    {
        if ($this->usesCareAggregate()) {
            $scopeIds = $this->selectedBranchScopeIds();

            if ($scopeIds === []) {
                return ReportCareQueueDailyAggregate::query()
                    ->from('report_care_queue_daily_aggregates as care_daily')
                    ->whereRaw('1 = 0');
            }

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
                ->whereIn('care_daily.branch_scope_id', $scopeIds)
                ->groupBy('care_daily.care_type', 'care_daily.care_status');
        }

        return $this->applyNoteBranchScope(
            Note::query()
                ->selectRaw('care_type, care_status, count(*) as total_count, max(care_at) as care_at')
                ->whereNotNull('care_type')
                ->whereNotNull('care_status')
        )->groupBy('care_type', 'care_status');
    }

    protected function getTableFilters(): array
    {
        $branchIds = $this->accessibleBranchIds();

        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => Branch::query()
                    ->when(
                        ! $this->isAdmin(),
                        fn (Builder $query) => $branchIds === []
                            ? $query->whereRaw('1 = 0')
                            : $query->whereIn('id', $branchIds),
                    )
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->query(fn (Builder $query): Builder => $query),
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
            $scopeIds = $this->selectedBranchScopeIds();

            if ($scopeIds === []) {
                $total = 0;
                $completed = 0;
                $planned = 0;

                return [
                    ['label' => 'Tổng chăm sóc', 'value' => number_format($total)],
                    ['label' => 'Hoàn thành', 'value' => number_format($completed)],
                    ['label' => 'Đã đặt lịch', 'value' => number_format($planned)],
                ];
            }

            $baseQuery->whereIn('branch_scope_id', $scopeIds);

            $total = (int) (clone $baseQuery)->sum('total_count');
            $completed = (int) (clone $baseQuery)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
                ->sum('total_count');
            $planned = (int) (clone $baseQuery)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))
                ->sum('total_count');
        } else {
            $baseQuery = $this->applyNoteBranchScope(
                Note::query()
                    ->whereNotNull('care_type')
                    ->whereNotNull('care_status')
            );
            $this->applyDateRange($baseQuery, 'care_at');

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

    protected function selectedBranchScopeIds(): array
    {
        $branchId = $this->getFilterValue('branch_id');

        if ($this->isAdmin()) {
            return filled($branchId)
                ? [(int) $branchId]
                : [0];
        }

        $accessibleBranchIds = $this->accessibleBranchIds();

        if (filled($branchId)) {
            $branchId = (int) $branchId;

            return in_array($branchId, $accessibleBranchIds, true)
                ? [$branchId]
                : [];
        }

        return $accessibleBranchIds;
    }

    protected function applyNoteBranchScope(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            $branchId = $this->getFilterValue('branch_id');

            if (! filled($branchId)) {
                return $query;
            }

            return $this->applyBranchConstraint($query, (int) $branchId);
        }

        $accessibleBranchIds = $this->accessibleBranchIds();

        if ($accessibleBranchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $branchId = $this->getFilterValue('branch_id');

        if (filled($branchId)) {
            $branchId = (int) $branchId;

            if (! in_array($branchId, $accessibleBranchIds, true)) {
                return $query->whereRaw('1 = 0');
            }

            return $this->applyBranchConstraint($query, $branchId);
        }

        return $query->where(function (Builder $scopeQuery) use ($accessibleBranchIds): void {
            $scopeQuery->whereIn('branch_id', $accessibleBranchIds)
                ->orWhere(function (Builder $legacyQuery) use ($accessibleBranchIds): void {
                    $legacyQuery->whereNull('branch_id')
                        ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->whereIn('first_branch_id', $accessibleBranchIds));
                });
        });
    }

    protected function applyBranchConstraint(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $scopeQuery) use ($branchId): void {
            $scopeQuery->where('branch_id', $branchId)
                ->orWhere(function (Builder $legacyQuery) use ($branchId): void {
                    $legacyQuery->whereNull('branch_id')
                        ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->where('first_branch_id', $branchId));
                });
        });
    }

    protected function accessibleBranchIds(): array
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return [];
        }

        return BranchAccess::accessibleBranchIds($authUser);
    }

    protected function isAdmin(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User && $authUser->hasRole('Admin');
    }
}
