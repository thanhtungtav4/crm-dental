<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\PatientPhoto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                CreateAction::make('create_normal')
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

                CreateAction::make('create_ortho')
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

                        // Standardized 9-photo Grid (3 columns)
                        Group::make()
                            ->schema([
                                // Row 1
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

                                // Row 2
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

                                // Row 3
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
                            ->columns(3),

                        Forms\Components\Textarea::make('description')
                            ->label('Nội dung:')
                            ->columnSpanFull(),
                    ]),

                CreateAction::make('create_xray')
                    ->label('Thêm ảnh X-quang')
                    ->modalWidth('3xl')
                    ->color('warning')
                    ->modalHeading('THÊM ẢNH X-QUANG')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['type'] = 'xray';
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
                            ->default('Ảnh X-quang'),
                        Forms\Components\FileUpload::make('content')
                            ->label('Upload ảnh X-quang')
                            ->image()
                            ->multiple()
                            ->directory('patient-photos/xray')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Nội dung:')
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Sửa')
                    ->modalHeading('Cập nhật ảnh bệnh nhân')
                    ->modalSubmitActionLabel('Lưu thay đổi')
                    ->form(fn (PatientPhoto $record): array => $this->getPhotoEditSchema($record)),
                DeleteAction::make()
                    ->label('Xóa')
                    ->modalHeading('Xóa ảnh bệnh nhân')
                    ->modalDescription('Bạn có chắc chắn muốn xóa ảnh này không?')
                    ->successNotificationTitle('Đã xóa ảnh bệnh nhân'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có ảnh bệnh nhân')
            ->emptyStateDescription('Thêm ảnh đầu tiên cho hồ sơ bệnh nhân này.')
            ->defaultSort('date', 'desc');
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
