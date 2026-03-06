<?php

namespace App\Filament\Resources\TreatmentMaterials\Tables;

use App\Models\TreatmentMaterial;
use App\Services\TreatmentMaterialUsageService;
use Filament\Actions\Action;
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
                'batch:id,material_id,batch_number,expiry_date',
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
                TextColumn::make('material.name')
                    ->label('Vật tư')
                    ->searchable(),
                TextColumn::make('batch.batch_number')
                    ->label('Lô')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('SL')
                    ->suffix(fn (TreatmentMaterial $record): string => filled($record->material?->unit) ? " {$record->material->unit}" : '')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Chi phí')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Người dùng')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('delete_usage')
                    ->label('Hoan tac ghi nhan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Da hoan tac ghi nhan vat tu')
                    ->visible(fn (TreatmentMaterial $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->action(function (TreatmentMaterial $record): void {
                        app(TreatmentMaterialUsageService::class)->delete($record);
                    }),
            ]);
    }
}
