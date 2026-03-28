<?php

namespace App\Filament\Pages\Reports;

use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use App\Services\OperationalKpiAlertReadModelService;
use App\Services\OperationalKpiSnapshotReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class OperationalKpiPack extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'KPI vận hành nha khoa';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'operational-kpi-pack';

    protected function getDateColumn(): ?string
    {
        return 'snapshot_date';
    }

    protected function getTableQuery(): Builder
    {
        return $this->operationalKpiSnapshots()
            ->baseQuery($this->resolvedVisibleBranchIds())
            ->with('branch')
            ->withCount([
                'alerts as active_alerts_count' => fn (Builder $query) => $query->whereIn('status', [
                    OperationalKpiAlert::STATUS_NEW,
                    OperationalKpiAlert::STATUS_ACK,
                ]),
            ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('snapshot_date')
                ->label('Ngày snapshot')
                ->date('d/m/Y')
                ->sortable(),
            TextColumn::make('branch.name')
                ->label('Chi nhánh')
                ->placeholder('Toàn hệ thống')
                ->searchable(),
            TextColumn::make('status')
                ->label('Trạng thái snapshot')
                ->badge()
                ->formatStateUsing(fn (?string $state) => $this->snapshotStatusLabel($state))
                ->color(fn (?string $state) => $state === ReportSnapshot::STATUS_SUCCESS ? 'success' : 'danger'),
            TextColumn::make('sla_status')
                ->label('SLA')
                ->badge()
                ->formatStateUsing(fn (?string $state) => $this->slaStatusLabel($state))
                ->color(fn (?string $state) => match ($state) {
                    ReportSnapshot::SLA_ON_TIME => 'success',
                    ReportSnapshot::SLA_LATE => 'warning',
                    ReportSnapshot::SLA_STALE => 'danger',
                    ReportSnapshot::SLA_MISSING => 'gray',
                    default => 'gray',
                }),
            TextColumn::make('generated_at')
                ->label('Tạo lúc')
                ->dateTime('d/m/Y H:i')
                ->placeholder('-')
                ->sortable(),
            TextColumn::make('sla_due_at')
                ->label('Hạn SLA')
                ->dateTime('d/m/Y H:i')
                ->placeholder('-')
                ->sortable(),
            TextColumn::make('payload.booking_to_visit_rate')
                ->label('Booking -> Visit')
                ->formatStateUsing(fn ($state) => $this->formatPercent($state)),
            TextColumn::make('payload.no_show_rate')
                ->label('No-show')
                ->formatStateUsing(fn ($state) => $this->formatPercent($state)),
            TextColumn::make('payload.treatment_acceptance_rate')
                ->label('Acceptance')
                ->formatStateUsing(fn ($state) => $this->formatPercent($state)),
            TextColumn::make('payload.chair_utilization_rate')
                ->label('Chair utilization')
                ->formatStateUsing(fn ($state) => $this->formatPercent($state)),
            TextColumn::make('payload.recall_rate')
                ->label('Recall')
                ->formatStateUsing(fn ($state) => $this->formatPercent($state)),
            TextColumn::make('payload.ltv_patient')
                ->label('LTV')
                ->formatStateUsing(fn ($state) => $this->formatCurrency($state)),
            TextColumn::make('payload.doctor_benchmark.0.doctor_name')
                ->label('Top doctor')
                ->placeholder('-'),
            TextColumn::make('active_alerts_count')
                ->label('Alert mở')
                ->badge()
                ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions()),
            SelectFilter::make('status')
                ->label('Trạng thái snapshot')
                ->options([
                    ReportSnapshot::STATUS_SUCCESS => 'Thành công',
                    ReportSnapshot::STATUS_FAILED => 'Thất bại',
                ]),
            SelectFilter::make('sla_status')
                ->label('SLA')
                ->options([
                    ReportSnapshot::SLA_ON_TIME => 'On-time',
                    ReportSnapshot::SLA_LATE => 'Late',
                    ReportSnapshot::SLA_STALE => 'Stale',
                    ReportSnapshot::SLA_MISSING => 'Missing',
                ]),
        ]);
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Ngày snapshot', 'value' => fn ($record) => $record->snapshot_date?->format('Y-m-d')],
            ['label' => 'Chi nhánh', 'value' => fn ($record) => $record->branch?->name ?? 'Toàn hệ thống'],
            ['label' => 'Trạng thái snapshot', 'value' => fn ($record) => $this->snapshotStatusLabel($record->status)],
            ['label' => 'SLA', 'value' => fn ($record) => $this->slaStatusLabel($record->sla_status)],
            ['label' => 'Booking -> Visit (%)', 'value' => fn ($record) => $record->payload['booking_to_visit_rate'] ?? 0],
            ['label' => 'No-show (%)', 'value' => fn ($record) => $record->payload['no_show_rate'] ?? 0],
            ['label' => 'Acceptance (%)', 'value' => fn ($record) => $record->payload['treatment_acceptance_rate'] ?? 0],
            ['label' => 'Chair utilization (%)', 'value' => fn ($record) => $record->payload['chair_utilization_rate'] ?? 0],
            ['label' => 'Recall (%)', 'value' => fn ($record) => $record->payload['recall_rate'] ?? 0],
            ['label' => 'Revenue per patient', 'value' => fn ($record) => $record->payload['revenue_per_patient'] ?? 0],
            ['label' => 'LTV', 'value' => fn ($record) => $record->payload['ltv_patient'] ?? 0],
        ];
    }

    public function getStats(): array
    {
        $visibleBranchIds = $this->resolvedVisibleBranchIds();
        $dateRange = (array) ($this->getFilterValue('date_range') ?? []);
        $snapshot = $this->operationalKpiSnapshots()->latestSnapshot(
            branchIds: $visibleBranchIds,
            filters: [
                'from' => $dateRange['from'] ?? null,
                'until' => $dateRange['until'] ?? null,
                'status' => $this->getFilterValue('status'),
                'sla_status' => $this->getFilterValue('sla_status'),
            ],
        );

        if (! $snapshot) {
            return [
                [
                    'label' => 'Snapshot KPI',
                    'value' => 'Chưa có',
                    'description' => 'Chưa tìm thấy dữ liệu theo bộ lọc hiện tại.',
                ],
            ];
        }

        $payload = (array) $snapshot->payload;
        $branchBenchmark = $this->operationalKpiSnapshots()->branchBenchmarkSummary(
            snapshot: $snapshot,
            branchIds: $visibleBranchIds,
        );
        $topDoctorBenchmark = data_get($payload, 'doctor_benchmark.0');
        $activeAlertCount = $this->operationalKpiAlerts()->activeAlertCountForSnapshot($snapshot->id);

        $stats = [
            ['label' => 'Booking -> Visit', 'value' => $this->formatPercent($payload['booking_to_visit_rate'] ?? 0)],
            ['label' => 'No-show rate', 'value' => $this->formatPercent($payload['no_show_rate'] ?? 0)],
            ['label' => 'Treatment acceptance', 'value' => $this->formatPercent($payload['treatment_acceptance_rate'] ?? 0)],
            ['label' => 'Chair utilization', 'value' => $this->formatPercent($payload['chair_utilization_rate'] ?? 0)],
            ['label' => 'Recall rate', 'value' => $this->formatPercent($payload['recall_rate'] ?? 0)],
            ['label' => 'Revenue/patient', 'value' => $this->formatCurrency($payload['revenue_per_patient'] ?? 0)],
            ['label' => 'LTV patient', 'value' => $this->formatCurrency($payload['ltv_patient'] ?? 0)],
            [
                'label' => 'SLA',
                'value' => $this->slaStatusLabel($snapshot->sla_status),
                'description' => 'Snapshot '.$snapshot->snapshot_date?->format('d/m/Y'),
            ],
        ];

        if ($branchBenchmark !== null) {
            $stats[] = [
                'label' => 'Benchmark chi nhánh',
                'value' => 'No-show '.$this->formatSignedPercent($branchBenchmark['no_show_delta']),
                'description' => 'Acceptance '.$this->formatSignedPercent($branchBenchmark['acceptance_delta']).' | Chair '.$this->formatSignedPercent($branchBenchmark['chair_delta']),
            ];
        }

        if (is_array($topDoctorBenchmark)) {
            $stats[] = [
                'label' => 'Benchmark bác sĩ',
                'value' => (string) data_get($topDoctorBenchmark, 'doctor_name', '-'),
                'description' => 'Visit '.$this->formatPercent(data_get($topDoctorBenchmark, 'booking_to_visit_rate', 0)).' | No-show '.$this->formatPercent(data_get($topDoctorBenchmark, 'no_show_rate', 0)),
            ];
        }

        $stats[] = [
            'label' => 'Alert KPI mở',
            'value' => (string) $activeAlertCount,
            'description' => $activeAlertCount > 0 ? 'Cần owner follow-up.' : 'Không có alert mở.',
        ];

        return $stats;
    }

    protected function buildFilteredSnapshotQuery(): Builder
    {
        $query = $this->operationalKpiSnapshots()
            ->baseQuery($this->resolvedVisibleBranchIds());
        $this->applyDateRange($query, 'snapshot_date');

        $status = $this->getFilterValue('status');
        if (filled($status)) {
            $query->where('status', $status);
        }

        $slaStatus = $this->getFilterValue('sla_status');
        if (filled($slaStatus)) {
            $query->where('sla_status', $slaStatus);
        }

        return $query;
    }

    protected function operationalKpiSnapshots(): OperationalKpiSnapshotReadModelService
    {
        return app(OperationalKpiSnapshotReadModelService::class);
    }

    protected function operationalKpiAlerts(): OperationalKpiAlertReadModelService
    {
        return app(OperationalKpiAlertReadModelService::class);
    }

    protected function snapshotStatusLabel(?string $status): string
    {
        return match ($status) {
            ReportSnapshot::STATUS_SUCCESS => 'Thành công',
            ReportSnapshot::STATUS_FAILED => 'Thất bại',
            default => 'Không xác định',
        };
    }

    protected function slaStatusLabel(?string $slaStatus): string
    {
        return match ($slaStatus) {
            ReportSnapshot::SLA_ON_TIME => 'On-time',
            ReportSnapshot::SLA_LATE => 'Late',
            ReportSnapshot::SLA_STALE => 'Stale',
            ReportSnapshot::SLA_MISSING => 'Missing',
            default => 'Không xác định',
        };
    }

    protected function formatPercent(mixed $value): string
    {
        return number_format((float) $value, 2).'%';
    }

    protected function formatCurrency(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').'đ';
    }

    protected function formatSignedPercent(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.number_format($value, 2).'%';
    }
}
