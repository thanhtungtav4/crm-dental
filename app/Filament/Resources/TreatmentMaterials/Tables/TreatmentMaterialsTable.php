<?php

namespace App\Filament\Resources\TreatmentMaterials\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreatmentMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'session.treatmentPlan.patient:id,full_name',
                'session.treatmentPlan.branch:id,name',
                'material:id,name,unit',
                'user:id,name',
            ]))
            ->columns([
                TextColumn::make('session.id')
                    ->label('Phiên')
                    ->sortable(),
                TextColumn::make('session.treatmentPlan.patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable(),
                BadgeColumn::make('session.treatmentPlan.branch.name')
                    ->label('Chi nhánh')
                    ->color('info'),
                TextColumn::make('material.name')->label('Vật tư')->searchable(),
                TextColumn::make('quantity')
                    ->label('SL')
                    ->suffix(fn ($record): string => filled($record->material?->unit) ? " {$record->material->unit}" : '')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost')->label('Chi phí')->money('VND')->sortable(),
                TextColumn::make('user.name')->label('Người dùng')->toggleable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Xóa để tạo lại')
                    ->tooltip('Vật tư đã ghi nhận không hỗ trợ sửa trực tiếp để đảm bảo tồn kho chính xác.')
                    ->requiresConfirmation(),
            ]);
    }
}
