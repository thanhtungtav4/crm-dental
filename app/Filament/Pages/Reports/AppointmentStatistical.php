<?php

namespace App\Filament\Pages\Reports;

use App\Models\Appointment;
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
        return Appointment::query()->with(['patient', 'doctor']);
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
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'scheduled' => 'Đã đặt',
                    'confirmed' => 'Đã xác nhận',
                    'completed' => 'Đã xong',
                    'cancelled' => 'Đã hủy',
                    'pending' => 'Chờ xử lý',
                    default => 'Không xác định',
                })
                ->color(fn (?string $state) => match ($state) {
                    'confirmed', 'completed' => 'success',
                    'cancelled' => 'danger',
                    'scheduled' => 'warning',
                    'pending' => 'info',
                    default => 'gray',
                }),
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
        ];
    }

    protected function formatAppointmentStatus(?string $state): string
    {
        return match ($state) {
            'scheduled' => 'Đã đặt',
            'confirmed' => 'Đã xác nhận',
            'completed' => 'Đã xong',
            'cancelled' => 'Đã hủy',
            'pending' => 'Chờ xử lý',
            default => 'Không xác định',
        };
    }

    public function getStats(): array
    {
        $baseQuery = Appointment::query();
        $this->applyDateRange($baseQuery, 'date');

        $total = (clone $baseQuery)->count();
        $new = (clone $baseQuery)->where('status', 'scheduled')->count();
        $cancelled = (clone $baseQuery)->where('status', 'cancelled')->count();
        $completed = (clone $baseQuery)->where('status', 'completed')->count();

        return [
            ['label' => 'Tổng lịch hẹn', 'value' => number_format($total)],
            ['label' => 'Lịch hẹn mới', 'value' => number_format($new)],
            ['label' => 'Lịch hẹn bị hủy', 'value' => number_format($cancelled)],
            ['label' => 'Đã xong', 'value' => number_format($completed)],
        ];
    }
}
