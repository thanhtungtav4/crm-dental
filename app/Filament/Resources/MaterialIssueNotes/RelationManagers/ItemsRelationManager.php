<?php

namespace App\Filament\Resources\MaterialIssueNotes\RelationManagers;

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

    protected static ?string $title = 'Vật tư xuất kho';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('material_id')
                    ->label('Vật tư')
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $material = \App\Models\Material::query()
                            ->select(['id', 'cost_price', 'sale_price'])
                            ->find((int) $state);

                        if ($material) {
                            $set('unit_cost', (float) ($material->cost_price ?? $material->sale_price ?? 0));
                        }
                    }),
                TextInput::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('unit_cost')
                    ->label('Đơn giá')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                TextColumn::make('material.name')
                    ->label('Vật tư')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Số lượng')
                    ->numeric(),
                TextColumn::make('unit_cost')
                    ->label('Đơn giá')
                    ->money('VND', divideBy: 1),
                TextColumn::make('total_cost')
                    ->label('Thành tiền')
                    ->money('VND', divideBy: 1),
                BadgeColumn::make('material.stock_qty')
                    ->label('Tồn kho hiện tại')
                    ->color('gray'),
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
