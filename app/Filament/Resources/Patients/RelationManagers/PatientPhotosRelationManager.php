<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\PatientPhoto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PatientPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Thư viện ảnh';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-photo';

    public function isReadOnly(): bool
    {
        return false;
    }

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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'normal' => 'Thông thường',
                        'ext' => 'Ảnh ngoài miệng',
                        'int' => 'Ảnh trong miệng',
                        'xray' => 'X-quang',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'normal' => 'gray',
                        'ext' => 'primary',
                        'int' => 'info',
                        'xray' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề / Ghi chú')
                    ->limit(50)
                    ->description(fn ($record) => $record->description),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'normal' => 'Thông thường',
                        'ext' => 'Ảnh ngoài miệng',
                        'int' => 'Ảnh trong miệng',
                        'xray' => 'X-quang',
                    ]),
            ])
            ->headerActions([
                Action::make('create_normal_photo')
                    ->label('Thêm ảnh thông thường')
                    ->color('gray')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Thêm ảnh thông thường')
                    ->modalSubmitActionLabel('Lưu ảnh')
                    ->form($this->getNormalPhotoCreateSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create([
                            ...$data,
                            'type' => 'normal',
                        ]);
                    }),
                Action::make('create_external_photo')
                    ->label('Thêm ảnh ngoài miệng')
                    ->color('primary')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Thêm ảnh ngoài miệng')
                    ->modalSubmitActionLabel('Lưu ảnh')
                    ->form($this->getExternalPhotoCreateSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create([
                            ...$data,
                            'type' => 'ext',
                        ]);
                    }),
                Action::make('create_internal_photo')
                    ->label('Thêm ảnh trong miệng')
                    ->color('info')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Thêm ảnh trong miệng')
                    ->modalSubmitActionLabel('Lưu ảnh')
                    ->form($this->getInternalPhotoCreateSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create([
                            ...$data,
                            'type' => 'int',
                        ]);
                    }),
                Action::make('create_xray_photo')
                    ->label('Thêm ảnh X-quang')
                    ->color('warning')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Thêm ảnh X-quang')
                    ->modalSubmitActionLabel('Lưu ảnh')
                    ->form($this->getXrayPhotoCreateSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create([
                            ...$data,
                            'type' => 'xray',
                        ]);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Sửa ảnh')
                    ->modalHeading('Cập nhật ảnh bệnh nhân')
                    ->modalSubmitActionLabel('Lưu thay đổi')
                    ->form(fn (PatientPhoto $record): array => $this->getPhotoEditSchema($record)),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa ảnh')
                    ->modalHeading('Xóa ảnh bệnh nhân')
                    ->modalDescription('Bạn có chắc chắn muốn xóa ảnh này không?')
                    ->successNotificationTitle('Đã xóa ảnh bệnh nhân'),
            ])
            ->emptyStateHeading('Chưa có ảnh bệnh nhân')
            ->emptyStateDescription('Thêm ảnh đầu tiên cho hồ sơ bệnh nhân này.')
            ->defaultSort('date', 'desc');
    }

    protected function getBasePhotoCreateSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('date')
                ->label('Ngày')
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('title')
                ->label('Tên')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Nội dung')
                ->columnSpanFull(),
        ];
    }

    protected function getNormalPhotoCreateSchema(): array
    {
        return [
            ...$this->getBasePhotoCreateSchema(),
            Forms\Components\FileUpload::make('content')
                ->label('Upload hình ảnh từ file')
                ->image()
                ->multiple()
                ->directory('patient-photos/normal')
                ->pasteable()
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getXrayPhotoCreateSchema(): array
    {
        return [
            ...$this->getBasePhotoCreateSchema(),
            Forms\Components\FileUpload::make('content')
                ->label('Upload ảnh X-quang')
                ->image()
                ->multiple()
                ->directory('patient-photos/xray')
                ->pasteable()
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getExternalPhotoCreateSchema(): array
    {
        return [
            ...$this->getBasePhotoCreateSchema(),
            Forms\Components\FileUpload::make('content')
                ->label('Upload ảnh ngoài miệng')
                ->image()
                ->multiple()
                ->directory('patient-photos/ext')
                ->pasteable()
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getInternalPhotoCreateSchema(): array
    {
        return [
            ...$this->getBasePhotoCreateSchema(),
            Forms\Components\FileUpload::make('content')
                ->label('Upload ảnh trong miệng')
                ->image()
                ->multiple()
                ->directory('patient-photos/int')
                ->pasteable()
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getPhotoEditSchema(PatientPhoto $record): array
    {
        $baseFields = [
            Forms\Components\DatePicker::make('date')
                ->label('Ngày')
                ->required(),
            Forms\Components\TextInput::make('title')
                ->label('Tên')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Nội dung')
                ->columnSpanFull(),
        ];

        return [
            ...$baseFields,
            Forms\Components\FileUpload::make('content')
                ->label(match ($record->type) {
                    'xray' => 'Upload ảnh X-quang',
                    'ext' => 'Upload ảnh ngoài miệng',
                    'int' => 'Upload ảnh trong miệng',
                    default => 'Upload hình ảnh từ file',
                })
                ->image()
                ->multiple()
                ->directory(match ($record->type) {
                    'xray' => 'patient-photos/xray',
                    'ext' => 'patient-photos/ext',
                    'int' => 'patient-photos/int',
                    default => 'patient-photos/normal',
                })
                ->pasteable()
                ->columnSpanFull(),
        ];
    }
}
