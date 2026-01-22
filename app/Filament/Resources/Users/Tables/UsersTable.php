<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->size(32)
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->toggleable(),
                TextColumn::make('name')->label('Tên')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone')->label('Điện thoại'),
                TextColumn::make('specialty')->label('Chuyên môn')->toggleable(),
                TextColumn::make('branch.name')->label('Chi nhánh')->toggleable(),
                TextColumn::make('roles.name')->label('Vai trò')->badge()->toggleable(),
                IconColumn::make('status')->label('Kích hoạt')->boolean(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
        ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
            DeleteBulkAction::make(),
                ]),
            ]);
    }
}
