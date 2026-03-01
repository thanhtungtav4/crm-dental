<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\TreatmentPlan;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TreatmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentPlans';

    protected static ?string $title = 'Kế hoạch điều trị';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã KH')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên kế hoạch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Nháp',
                        'approved' => 'Đã duyệt',
                        'in_progress' => 'Đang thực hiện',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                        default => 'Không xác định',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'secondary',
                        'approved' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Tổng chi phí')
                    ->money('VND', locale: 'vi')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Tiến độ')
                    ->suffix('%')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'approved' => 'Đã duyệt',
                        'in_progress' => 'Đang thực hiện',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->url(fn (TreatmentPlan $record): string => route('filament.admin.resources.treatment-plans.edit', [
                        'record' => $record->id,
                        'return_url' => request()->fullUrl(),
                    ])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có kế hoạch điều trị')
            ->emptyStateDescription('Tạo kế hoạch điều trị đầu tiên cho bệnh nhân này')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Tạo kế hoạch mới')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn () => route('filament.admin.resources.treatment-plans.create', [
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
