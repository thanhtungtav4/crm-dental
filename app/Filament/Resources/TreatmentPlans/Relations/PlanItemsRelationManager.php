<?php

namespace App\Filament\Resources\TreatmentPlans\Relations;

use App\Models\PlanItem;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'planItems';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->label('Hạng mục')->required(),
            Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
            Forms\Components\TextInput::make('price')->numeric()->default(0)->required(),
            Forms\Components\Repeater::make('estimated_supplies')
                ->label('Vật tư dự kiến')
                ->schema([
                    Forms\Components\Select::make('material_id')
                        ->label('Vật tư')
                        ->options(fn() => \App\Models\Material::query()->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('qty')->numeric()->default(1),
                ])->collapsed(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Hạng mục')->searchable(),
                TextColumn::make('quantity')->label('SL')->sortable(),
                TextColumn::make('price')->label('Giá')->money('VND')->sortable(),
                TextColumn::make('total')->label('Thành tiền')->state(fn(PlanItem $rec) => $rec->quantity * $rec->price)->money('VND'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
