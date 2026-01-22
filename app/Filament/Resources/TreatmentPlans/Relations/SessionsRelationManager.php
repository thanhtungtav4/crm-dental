<?php

namespace App\Filament\Resources\TreatmentPlans\Relations;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('doctor_id')
                ->label('Bác sĩ')
                ->options(fn() => \App\Models\User::role('Doctor')->pluck('name', 'id'))
                ->searchable()->preload(),
            Forms\Components\DateTimePicker::make('start_at')->label('Bắt đầu'),
            Forms\Components\DateTimePicker::make('end_at')->label('Kết thúc'),
            Forms\Components\DateTimePicker::make('performed_at')->label('Thời gian'),
            Forms\Components\Textarea::make('procedure')->label('Thủ thuật')->rows(2),
            Forms\Components\Select::make('status')->options([
                'scheduled' => 'Đã đặt',
                'done' => 'Hoàn tất',
                'follow_up' => 'Tái khám',
            ])->default('scheduled'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('performed_at')->label('Thời gian')->dateTime()->sortable(),
                TextColumn::make('start_at')->label('Bắt đầu')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_at')->label('Kết thúc')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('doctor.name')->label('Bác sĩ'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->icon(fn(?string $s) => \App\Support\StatusBadge::icon($s))
                    ->color(fn(?string $s) => \App\Support\StatusBadge::color($s)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tạo buổi điều trị mới')
                    ->after(function ($record) {
                        // Optionally pre-fill supplies from plan items in future
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
