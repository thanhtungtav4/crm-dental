<?php

namespace App\Filament\Pages\Reports;

use App\Models\Note;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class CustomsCareStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê CSKH';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'customs-care-statistical';

    protected function getDateColumn(): ?string
    {
        return 'care_at';
    }

    protected function getTableQuery(): Builder
    {
        return Note::query()
            ->selectRaw('care_type, care_status, count(*) as total_count, max(care_at) as care_at')
            ->groupBy('care_type', 'care_status');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('care_type')
                ->label('Phân loại')
                ->formatStateUsing(fn (?string $state) => Arr::get($this->getCareTypeOptions(), $state, 'Chăm sóc chung'))
                ->badge(),
            TextColumn::make('care_status')
                ->label('Trạng thái')
                ->formatStateUsing(fn (?string $state) => Arr::get($this->getCareStatusOptions(), $state, 'Chưa xác định'))
                ->badge(),
            TextColumn::make('total_count')
                ->label('Số lượng')
                ->numeric(),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Phân loại', 'value' => fn ($record) => Arr::get($this->getCareTypeOptions(), $record->care_type, 'Chăm sóc chung')],
            ['label' => 'Trạng thái', 'value' => fn ($record) => Arr::get($this->getCareStatusOptions(), $record->care_status, 'Chưa xác định')],
            ['label' => 'Số lượng', 'value' => fn ($record) => $record->total_count],
        ];
    }

    public function getStats(): array
    {
        $baseQuery = Note::query();
        $this->applyDateRange($baseQuery, 'care_at');

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('care_status', 'completed')->count();
        $planned = (clone $baseQuery)->where('care_status', 'planned')->count();

        return [
            ['label' => 'Tổng chăm sóc', 'value' => number_format($total)],
            ['label' => 'Hoàn thành', 'value' => number_format($completed)],
            ['label' => 'Đã đặt lịch', 'value' => number_format($planned)],
        ];
    }

    protected function getCareTypeOptions(): array
    {
        return [
            'appointment_reminder' => 'Nhắc lịch hẹn',
            'medication_reminder' => 'Nhắc lịch uống thuốc',
            'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
            'birthday_care' => 'Chăm sóc sinh nhật',
            'general_care' => 'Chăm sóc chung',
        ];
    }

    protected function getCareStatusOptions(): array
    {
        return [
            'planned' => 'Đã đặt lịch',
            'completed' => 'Đã chăm sóc',
            'no_response' => 'Không phản hồi',
            'cancelled' => 'Đã hủy',
        ];
    }
}
