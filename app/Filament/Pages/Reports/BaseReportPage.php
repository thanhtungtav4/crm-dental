<?php

namespace App\Filament\Pages\Reports;

use App\Support\Exports\ExportsCsv;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page implements HasTable
{
    use InteractsWithTable;
    use ExportsCsv;

    protected string $view = 'filament.pages.reports.base-report';

    public function getStats(): array
    {
        return [];
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
        return $slug . '_' . now()->format('Ymd_His') . '.csv';
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
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, $dateColumn)),
        ];
    }

    protected function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns($this->getTableColumns())
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

    abstract protected function getTableQuery(): Builder;

    abstract protected function getTableColumns(): array;
}
