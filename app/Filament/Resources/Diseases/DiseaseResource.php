<?php

namespace App\Filament\Resources\Diseases;

use App\Models\Disease;
use App\Models\DiseaseGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiseaseResource extends Resource
{
    protected static ?string $model = Disease::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Cấu hình bệnh';

    protected static ?string $modelLabel = 'Bệnh';

    protected static ?string $pluralModelLabel = 'Bệnh';

    protected static ?int $navigationSort = 2;

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
                        Select::make('disease_group_id')
                            ->label('Nhóm bệnh')
                            ->relationship('diseaseGroup', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Tên nhóm')
                                    ->required(),
                            ]),

                        TextInput::make('code')
                            ->label('Mã bệnh')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('VD: K02'),

                        TextInput::make('name')
                            ->label('Tên bệnh')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('VD: Sâu răng'),

                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Đang sử dụng')
                            ->default(true),
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
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Tên bệnh')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('diseaseGroup.name')
                    ->label('Nhóm')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('disease_group_id')
                    ->label('Nhóm bệnh')
                    ->relationship('diseaseGroup', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Hoạt động'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => DiseaseResource\Pages\ListDiseases::route('/'),
            'create' => DiseaseResource\Pages\CreateDisease::route('/create'),
            'edit' => DiseaseResource\Pages\EditDisease::route('/{record}/edit'),
        ];
    }
}
