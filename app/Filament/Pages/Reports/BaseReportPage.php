<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\Exports\ExportsCsv;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page implements HasTable
{
    use ExportsCsv;
    use InteractsWithTable;

    protected string $view = 'filament.pages.reports.base-report';

    public function getStats(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->hasAnyRole(['Admin', 'Manager'])
            && $authUser->hasAnyAccessibleBranch();
    }

    protected function getDateColumn(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Xuất CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        return $this->streamCsv(
            $this->getExportFileName(),
            $this->getExportColumns(),
            $this->getTableQueryForExport(),
        );
    }

    protected function getExportFileName(): string
    {
        $slug = static::$slug ?? 'report';

        return $slug.'_'.now()->format('Ymd_His').'.csv';
    }

    protected function getExportColumns(): array
    {
        return [];
    }

    protected function getTableFilters(): array
    {
        $dateColumn = $this->getDateColumn();
        if (! $dateColumn) {
            return [];
        }

        return [
            Filter::make('date_range')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyTableDateRangeFilter($query, $data)),
        ];
    }

    protected function applyTableDateRangeFilter(Builder $query, array $data): Builder
    {
        $dateColumn = $this->getDateColumn();

        if (! $dateColumn) {
            return $query;
        }

        return $this->applyDateRangeFilter($query, $data, $dateColumn);
    }

    protected function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns($this->getTableColumns())
            ->defaultKeySort(false)
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->emptyStateHeading('Chưa có dữ liệu')
            ->emptyStateDescription('Dữ liệu báo cáo sẽ hiển thị tại đây.');
    }

    protected function applyDateRangeFilter(Builder $query, array $data, string $column): Builder
    {
        if (! empty($data['from'])) {
            $query->whereDate($column, '>=', $data['from']);
        }

        if (! empty($data['until'])) {
            $query->whereDate($column, '<=', $data['until']);
        }

        return $query;
    }

    protected function currentUser(): ?User
    {
        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    protected function isAdmin(): bool
    {
        $authUser = $this->currentUser();

        return $authUser instanceof User && $authUser->hasRole('Admin');
    }

    /**
     * @return array<int, int>
     */
    protected function accessibleBranchIds(): array
    {
        $authUser = $this->currentUser();

        return BranchAccess::accessibleBranchIds($authUser, true);
    }

    /**
     * @return array<int, string>
     */
    protected function branchFilterOptions(): array
    {
        if ($this->isAdmin()) {
            return Branch::query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return BranchAccess::branchOptionsForCurrentUser();
    }

    protected function rawSelectedBranchId(string $filterName = 'branch_id'): ?int
    {
        $branchId = $this->getFilterValue($filterName);

        if (! filled($branchId)) {
            return null;
        }

        return (int) $branchId;
    }

    /**
     * @return array<int, int>|null
     */
    protected function resolvedVisibleBranchIds(string $filterName = 'branch_id'): ?array
    {
        $selectedBranchId = $this->rawSelectedBranchId($filterName);

        if ($this->isAdmin()) {
            return $selectedBranchId !== null
                ? [$selectedBranchId]
                : null;
        }

        $accessibleBranchIds = $this->accessibleBranchIds();

        if ($accessibleBranchIds === []) {
            return [];
        }

        if ($selectedBranchId !== null) {
            return in_array($selectedBranchId, $accessibleBranchIds, true)
                ? [$selectedBranchId]
                : [];
        }

        return $accessibleBranchIds;
    }

    protected function applyDirectBranchScope(
        Builder $query,
        string $column = 'branch_id',
        string $filterName = 'branch_id',
    ): Builder {
        $branchIds = $this->resolvedVisibleBranchIds($filterName);

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    protected function applyRelatedBranchScope(
        Builder $query,
        string $relation,
        string $column = 'branch_id',
        string $filterName = 'branch_id',
    ): Builder {
        $branchIds = $this->resolvedVisibleBranchIds($filterName);

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, fn (Builder $relationQuery) => $relationQuery->whereIn($column, $branchIds));
    }

    protected function getDateRangeFromFilters(): array
    {
        $from = data_get($this->tableFilters ?? [], 'date_range.from');
        $until = data_get($this->tableFilters ?? [], 'date_range.until');

        return [$from, $until];
    }

    protected function applyDateRange(Builder $query, ?string $column = null): Builder
    {
        if (! $column) {
            return $query;
        }

        [$from, $until] = $this->getDateRangeFromFilters();

        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($until) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }

    /**
     * Grouped report queries often do not expose a model primary key.
     * Generate a deterministic key from selected attributes to keep Filament tables stable.
     */
    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return parent::getTableRecordKey($record);
        }

        $key = $record->getKey();

        if (filled($key)) {
            return (string) $key;
        }

        $attributes = $record->getAttributes();
        ksort($attributes);

        return sha1(static::class.'|'.serialize($attributes));
    }

    protected function getFilterValue(string $filterName): mixed
    {
        return data_get($this->tableFilters ?? [], "{$filterName}.value");
    }

    abstract protected function getTableQuery(): Builder;

    abstract protected function getTableColumns(): array;
}
