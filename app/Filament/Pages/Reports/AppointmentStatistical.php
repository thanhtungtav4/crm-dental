<?php

namespace App\Filament\Pages\Reports;

use App\Models\Appointment;
use App\Models\VisitEpisode;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class AppointmentStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê lịch hẹn';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'appointment-statistical';

    protected function getDateColumn(): ?string
    {
        return 'date';
    }

    protected function getTableQuery(): Builder
    {
        return Appointment::query()->with(['patient', 'doctor', 'visitEpisode']);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ tên')
                ->searchable(),
            TextColumn::make('patient.phone')
                ->label('Số điện thoại')
                ->searchable(),
            TextColumn::make('date')
                ->label('Ngày')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('time_range_label')
                ->label('Khung giờ'),
            TextColumn::make('doctor.name')
                ->label('Bác sĩ')
                ->sortable(),
            TextColumn::make('status')
                ->label('Trạng thái')
                ->badge()
                ->formatStateUsing(fn (?string $state) => Appointment::statusLabel($state))
                ->color(fn (?string $state) => Appointment::statusColor($state))
                ->icon(fn (?string $state) => Appointment::statusIcon($state)),
            TextColumn::make('visitEpisode.waiting_minutes')
                ->label('Waiting (phút)')
                ->getStateUsing(fn ($record) => $record->visitEpisode?->waiting_minutes ?? '-')
                ->sortable(),
            TextColumn::make('visitEpisode.chair_minutes')
                ->label('Chair (phút)')
                ->getStateUsing(fn ($record) => $record->visitEpisode?->chair_minutes ?? '-')
                ->sortable(),
            TextColumn::make('visitEpisode.overrun_minutes')
                ->label('Overrun (phút)')
                ->getStateUsing(fn ($record) => $record->visitEpisode?->overrun_minutes ?? '-')
                ->sortable(),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Số điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Ngày', 'value' => fn ($record) => $record->date],
            ['label' => 'Khung giờ', 'value' => fn ($record) => $record->time_range_label],
            ['label' => 'Bác sĩ', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Trạng thái', 'value' => fn ($record) => $this->formatAppointmentStatus($record->status)],
            ['label' => 'Waiting (phút)', 'value' => fn ($record) => $record->visitEpisode?->waiting_minutes],
            ['label' => 'Chair (phút)', 'value' => fn ($record) => $record->visitEpisode?->chair_minutes],
            ['label' => 'Overrun (phút)', 'value' => fn ($record) => $record->visitEpisode?->overrun_minutes],
        ];
    }

    protected function formatAppointmentStatus(?string $state): string
    {
        return Appointment::statusLabel($state);
    }

    public function getStats(): array
    {
        $baseQuery = Appointment::query();
        $this->applyDateRange($baseQuery, 'date');

        $total = (clone $baseQuery)->count();
        $new = (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))->count();
        $cancelled = (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_CANCELLED]))->count();
        $completed = (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]))->count();

        $episodeQuery = VisitEpisode::query()
            ->whereHas('appointment', function (Builder $query): void {
                $this->applyDateRange($query, 'date');
            });

        $avgWaiting = round((float) ((clone $episodeQuery)->whereNotNull('waiting_minutes')->avg('waiting_minutes') ?? 0), 1);
        $avgChair = round((float) ((clone $episodeQuery)->whereNotNull('chair_minutes')->avg('chair_minutes') ?? 0), 1);
        $avgOverrun = round((float) ((clone $episodeQuery)->whereNotNull('overrun_minutes')->avg('overrun_minutes') ?? 0), 1);

        return [
            ['label' => 'Tổng lịch hẹn', 'value' => number_format($total)],
            ['label' => 'Lịch hẹn mới', 'value' => number_format($new)],
            ['label' => 'Lịch hẹn bị hủy', 'value' => number_format($cancelled)],
            ['label' => 'Hoàn thành', 'value' => number_format($completed)],
            ['label' => 'Waiting TB (phút)', 'value' => number_format($avgWaiting, 1)],
            ['label' => 'Chair TB (phút)', 'value' => number_format($avgChair, 1)],
            ['label' => 'Overrun TB (phút)', 'value' => number_format($avgOverrun, 1)],
        ];
    }
}
