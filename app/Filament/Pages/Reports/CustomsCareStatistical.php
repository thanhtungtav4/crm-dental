<?php

namespace App\Filament\Pages\Reports;

use App\Models\Note;
use App\Models\User;
use App\Services\HotReportAggregateReadModelService;
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

    /**
     * @var array{key:string,value:bool}|null
     */
    protected ?array $usesCareAggregateDecision = null;

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return parent::canAccess()
            && $authUser instanceof User
            && $authUser->can('ViewAny:Note');
    }

    protected function getDateColumn(): ?string
    {
        return $this->usesCareAggregate()
            ? 'snapshot_date'
            : 'care_at';
    }

    protected function applyTableDateRangeFilter(Builder $query, array $data): Builder
    {
        return $this->applyDateRangeFilter(
            $query,
            $data,
            $this->usesCareAggregate() ? 'snapshot_date' : 'care_at',
        );
    }

    protected function getTableQuery(): Builder
    {
        if ($this->usesCareAggregate()) {
            $scopeIds = $this->selectedBranchScopeIds();

            return $this->hotReportAggregates()->careBreakdownQuery($scopeIds);
        }

        return $this->hotReportAggregates()->liveCareBreakdownQuery($this->resolvedVisibleBranchIds());
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions())
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
        [$from, $until] = $this->getDateRangeFromFilters();

        if ($this->usesCareAggregate()) {
            $scopeIds = $this->selectedBranchScopeIds();
            $summary = $this->hotReportAggregates()->careSummary($scopeIds, $from, $until);
            $total = $summary['total'];
            $completed = $summary['completed'];
            $planned = $summary['planned'];
        } else {
            $summary = $this->hotReportAggregates()->liveCareSummary(
                $this->resolvedVisibleBranchIds(),
                $from,
                $until,
            );
            $total = $summary['total'];
            $completed = $summary['completed'];
            $planned = $summary['planned'];
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

    protected function usesCareAggregate(): bool
    {
        [$from, $until] = $this->getDateRangeFromFilters();
        $scopeIds = $this->selectedBranchScopeIds();
        $decisionKey = md5(json_encode([
            'from' => $from,
            'until' => $until,
            'scope_ids' => $scopeIds,
        ]) ?: '');

        if (($this->usesCareAggregateDecision['key'] ?? null) === $decisionKey) {
            return (bool) $this->usesCareAggregateDecision['value'];
        }

        $usesAggregate = $this->hotReportAggregates()
            ->shouldUseCareAggregate($scopeIds, $from, $until);

        $this->usesCareAggregateDecision = [
            'key' => $decisionKey,
            'value' => $usesAggregate,
        ];

        return $usesAggregate;
    }

    protected function hotReportAggregates(): HotReportAggregateReadModelService
    {
        return app(HotReportAggregateReadModelService::class);
    }

    protected function selectedBranchScopeIds(): array
    {
        $branchId = $this->rawSelectedBranchId();

        if ($this->isAdmin()) {
            return $branchId !== null
                ? [$branchId]
                : [0];
        }

        $accessibleBranchIds = $this->accessibleBranchIds();

        if ($branchId !== null) {
            return in_array($branchId, $accessibleBranchIds, true)
                ? [$branchId]
                : [];
        }

        return $accessibleBranchIds;
    }
}
