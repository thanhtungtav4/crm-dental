<?php

namespace App\Filament\Resources\FactoryOrders\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Hạng mục labo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('item_name')
                    ->label('Tên item')
                    ->required()
                    ->maxLength(255),
                Select::make('service_id')
                    ->label('Dịch vụ liên quan')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('tooth_number')
                    ->label('Răng số')
                    ->maxLength(32),
                TextInput::make('material')
                    ->label('Chất liệu')
                    ->maxLength(255),
                TextInput::make('shade')
                    ->label('Màu')
                    ->maxLength(120),
                TextInput::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('Đơn giá')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'ordered' => 'Đã đặt',
                        'in_progress' => 'Đang làm',
                        'delivered' => 'Đã giao',
                        'cancelled' => 'Đã hủy',
                    ])
                    ->default('ordered')
                    ->required(),
                Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                TextColumn::make('item_name')
                    ->label('Tên item')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('tooth_number')
                    ->label('Răng số')
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label('S.L')
                    ->numeric(),
                TextColumn::make('unit_price')
                    ->label('Đơn giá')
                    ->money('VND', divideBy: 1),
                TextColumn::make('total_price')
                    ->label('Thành tiền')
                    ->money('VND', divideBy: 1),
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ordered' => 'Đã đặt',
                        'in_progress' => 'Đang làm',
                        'delivered' => 'Đã giao',
                        'cancelled' => 'Đã hủy',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'ordered',
                        'info' => 'in_progress',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ]),
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
