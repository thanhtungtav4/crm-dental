<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Người liên hệ';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label('Họ tên')
                    ->required()
                    ->maxLength(255),
                TextInput::make('relationship')
                    ->label('Mối quan hệ')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Số điện thoại')
                    ->tel()
                    ->maxLength(32),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Checkbox::make('is_primary')
                    ->label('Liên hệ chính'),
                Checkbox::make('is_emergency')
                    ->label('Liên hệ khẩn cấp'),
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable(),
                TextColumn::make('relationship')
                    ->label('Mối quan hệ')
                    ->default('-'),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable()
                    ->default('-'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->default('-')
                    ->toggleable(),
                IconColumn::make('is_primary')
                    ->label('Chính')
                    ->boolean(),
                IconColumn::make('is_emergency')
                    ->label('Khẩn cấp')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
