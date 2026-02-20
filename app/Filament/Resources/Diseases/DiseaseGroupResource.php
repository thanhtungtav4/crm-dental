<?php

namespace App\Filament\Resources\Diseases;

use App\Models\DiseaseGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiseaseGroupResource extends Resource
{
    protected static ?string $model = DiseaseGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Danh mục bệnh';

    protected static ?string $modelLabel = 'Danh mục bệnh';

    protected static ?string $pluralModelLabel = 'Danh mục bệnh';

    protected static ?int $navigationSort = 1;

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
                        TextInput::make('name')
                            ->label('Tên nhóm')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3),

                        TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên nhóm')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('diseases_count')
                    ->label('Số bệnh')
                    ->counts('diseases')
                    ->badge()
                    ->color('info'),

                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            DiseaseGroupResource\RelationManagers\DiseasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => DiseaseGroupResource\Pages\ListDiseaseGroups::route('/'),
            'create' => DiseaseGroupResource\Pages\CreateDiseaseGroup::route('/create'),
            'edit' => DiseaseGroupResource\Pages\EditDiseaseGroup::route('/{record}/edit'),
        ];
    }
}
