<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\PatientPhoto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
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
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'normal' => 'Thông thường',
                        'ortho' => 'Chỉnh nha',
                        'xray' => 'X-quang',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'normal' => 'gray',
                        'ortho' => 'primary',
                        'xray' => 'warning',
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
                Action::make('create_ortho_photo')
                    ->label('Thêm ảnh chỉnh nha')
                    ->color('primary')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Thêm ảnh chỉnh nha')
                    ->modalSubmitActionLabel('Lưu ảnh')
                    ->form($this->getOrthoPhotoCreateSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create([
                            ...$data,
                            'type' => 'ortho',
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
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getOrthoPhotoCreateSchema(): array
    {
        return [
            ...$this->getBasePhotoCreateSchema(),
            Group::make()
                ->schema([
                    Forms\Components\FileUpload::make('content.lateral')
                        ->label('Nghiêng')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.frontal')
                        ->label('Chính diện')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.profile_45')
                        ->label('Nghiêng 45°')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.maxillary')
                        ->label('Hàm trên')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.middle_1')
                        ->label('Giữa (trong miệng)')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.mandibular')
                        ->label('Hàm dưới')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.right')
                        ->label('Bên phải')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.middle_2')
                        ->label('Giữa (mặt trước)')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                    Forms\Components\FileUpload::make('content.left')
                        ->label('Bên trái')
                        ->image()
                        ->directory('patient-photos/ortho')
                        ->imageEditor(),
                ])
                ->columns(3)
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

        if ($record->type === 'ortho') {
            return [
                ...$baseFields,
                Group::make()
                    ->schema([
                        Forms\Components\FileUpload::make('content.lateral')
                            ->label('Nghiêng')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.frontal')
                            ->label('Chính diện')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.profile_45')
                            ->label('Nghiêng 45°')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.maxillary')
                            ->label('Hàm trên')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.middle_1')
                            ->label('Giữa (trong miệng)')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.mandibular')
                            ->label('Hàm dưới')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.right')
                            ->label('Bên phải')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.middle_2')
                            ->label('Giữa (mặt trước)')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('content.left')
                            ->label('Bên trái')
                            ->image()
                            ->directory('patient-photos/ortho')
                            ->imageEditor(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ];
        }

        return [
            ...$baseFields,
            Forms\Components\FileUpload::make('content')
                ->label($record->type === 'xray' ? 'Upload ảnh X-quang' : 'Upload hình ảnh từ file')
                ->image()
                ->multiple()
                ->directory($record->type === 'xray' ? 'patient-photos/xray' : 'patient-photos/normal')
                ->columnSpanFull(),
        ];
    }
}
