<?php

namespace App\Filament\Resources\ToothConditions;

use App\Models\ToothCondition;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ToothConditionResource extends Resource
{
    protected static ?string $model = ToothCondition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Danh mục tình trạng răng';

    protected static ?string $modelLabel = 'Tình trạng răng';

    protected static ?string $pluralModelLabel = 'Tình trạng răng';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Cài đặt hệ thống';
    }

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('code')
                            ->label('Mã')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state))),

                        TextInput::make('name')
                            ->label('Tên hiển thị')
                            ->required()
                            ->maxLength(255),

                        Select::make('category')
                            ->label('Nhóm')
                            ->options(fn (): array => ToothCondition::getCategoryOptions())
                            ->searchable()
                            ->native(false),

                        TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        ColorPicker::make('color')
                            ->label('Màu hiển thị')
                            ->default('#9ca3af'),

                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Tên hiển thị')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('category')
                    ->label('Nhóm')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable(),

                TextColumn::make('color')
                    ->label('Màu')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: '#9ca3af'),

                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category')
                    ->label('Nhóm')
                    ->options(fn (): array => ToothCondition::getCategoryOptions()),
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

    public static function getPages(): array
    {
        return [
            'index' => ToothConditionResource\Pages\ListToothConditions::route('/'),
            'create' => ToothConditionResource\Pages\CreateToothCondition::route('/create'),
            'edit' => ToothConditionResource\Pages\EditToothCondition::route('/{record}/edit'),
        ];
    }
}
