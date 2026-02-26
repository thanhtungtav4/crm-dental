<?php

namespace App\Filament\Pages\Reports;

use App\Models\Note;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

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
                ->formatStateUsing(fn (?string $state) => $this->getCareTypeOptions()[$state] ?? 'Chăm sóc chung')
                ->badge(),
            TextColumn::make('care_status')
                ->label('Trạng thái')
                ->formatStateUsing(fn (?string $state) => Note::careStatusLabel($state))
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
            ['label' => 'Phân loại', 'value' => fn ($record) => $this->getCareTypeOptions()[$record->care_type] ?? 'Chăm sóc chung'],
            ['label' => 'Trạng thái', 'value' => fn ($record) => Note::careStatusLabel($record->care_status)],
            ['label' => 'Số lượng', 'value' => fn ($record) => $record->total_count],
        ];
    }

    public function getStats(): array
    {
        $baseQuery = Note::query();
        $this->applyDateRange($baseQuery, 'care_at');

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))->count();
        $planned = (clone $baseQuery)->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))->count();

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
            'no_show_recovery' => 'Recovery no-show',
            'recall_recare' => 'Recall / Re-care',
            'payment_reminder' => 'Nhắc thanh toán',
            'medication_reminder' => 'Nhắc lịch uống thuốc',
            'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
            'treatment_plan_follow_up' => 'Theo dõi chưa chốt kế hoạch',
            'birthday_care' => 'Chăm sóc sinh nhật',
            'general_care' => 'Chăm sóc chung',
        ];
    }

    protected function getCareStatusOptions(): array
    {
        return Note::careStatusOptions();
    }
}
