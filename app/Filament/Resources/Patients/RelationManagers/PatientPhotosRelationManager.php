<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Thư viện ảnh';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-photo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Ngày')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại ảnh')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'normal' => 'Thông thường',
                        'ortho' => 'Chỉnh nha',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'normal' => 'gray',
                        'ortho' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề / Ghi chú')
                    ->limit(50)
                    ->description(fn($record) => $record->type === 'ortho' ? 'Gồm 9 góc chụp' : $record->description),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'normal' => 'Thông thường',
                        'ortho' => 'Chỉnh nha',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make('create_normal')
                    ->label('Thêm ảnh thông thường')
                    ->modalWidth('3xl')
                    ->color('gray')
                    ->modalHeading('THÊM ẢNH THÔNG THƯỜNG')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['type'] = 'normal';
                        return $data;
                    })
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Ngày')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('title')
                            ->label('Tên')
                            ->required()
                            ->default('Ảnh thông thường'),
                        Forms\Components\FileUpload::make('content')
                            ->label('Upload hình ảnh từ file')
                            ->image()
                            ->multiple()
                            ->directory('patient-photos/normal')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Nội dung:')
                            ->columnSpanFull(),
                    ]),

                Tables\Actions\CreateAction::make('create_ortho')
                    ->label('Thêm ảnh chỉnh nha')
                    ->modalWidth('5xl')
                    ->color('primary')
                    ->modalHeading('THÊM ẢNH CHỈNH NHA')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['type'] = 'ortho';
                        $data['title'] = 'Bộ ảnh chỉnh nha ngày ' . now()->format('d/m/Y');
                        return $data;
                    })
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Ngày')
                            ->required()
                            ->default(now()),

                        // Standardized 9-photo Grid
                        Forms\Components\Grid::make(3)
                            ->schema([
                                // Row 1
                                Forms\Components\FileUpload::make('content.lateral')
                                    ->label('Lateral view')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.frontal')
                                    ->label('Frontal view')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.profile_45')
                                    ->label('45° profile')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),

                                // Row 2
                                Forms\Components\FileUpload::make('content.maxillary')
                                    ->label('Maxillary')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.middle_1')
                                    ->label('Middle (Intra)')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.mandibular')
                                    ->label('Mandibular')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),

                                // Row 3
                                Forms\Components\FileUpload::make('content.right')
                                    ->label('Right')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.middle_2')
                                    ->label('Middle (Front)')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('content.left')
                                    ->label('Left')
                                    ->image()
                                    ->directory('patient-photos/ortho')
                                    ->imageEditor(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Nội dung:')
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
