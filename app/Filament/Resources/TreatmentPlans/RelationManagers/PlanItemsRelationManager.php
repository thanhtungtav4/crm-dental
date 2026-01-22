<?php

namespace App\Filament\Resources\TreatmentPlans\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlanItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'planItems';

    protected static ?string $title = 'Các hạng mục điều trị';

    protected static ?string $modelLabel = 'hạng mục';

    protected static ?string $pluralModelLabel = 'Các hạng mục';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin dịch vụ điều trị')
                    ->schema([
                        Select::make('service_id')
                            ->label('Dịch vụ')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $service = \App\Models\Service::find($state);
                                    if ($service) {
                                        $set('name', $service->name);
                                        $set('estimated_cost', $service->price);
                                    }
                                }
                            })
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('Tên hạng mục')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Tự động lấy từ dịch vụ')
                            ->columnSpan(1),
                        TextInput::make('tooth_number')
                            ->label('🦷 Vị trí răng')
                            ->placeholder('VD: 11, 11-14, 11,12,13')
                            ->helperText('Nhập 1 răng (11), hoặc nhiều răng (11,12,13), hoặc khoảng (11-14)')
                            ->maxLength(50)
                            ->columnSpan(1),
                        Select::make('tooth_notation')
                            ->label('Hệ thống đánh số')
                            ->options([
                                'fdi' => 'FDI (11-48)',
                                'universal' => 'Universal (1-32)',
                            ])
                            ->default('fdi')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Số lượng & Chi phí')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Số lượng')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('required_visits')
                            ->label('Số lần khám cần thiết')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->helperText('Số lần khám dự kiến để hoàn thành hạng mục này')
                            ->columnSpan(1),
                        TextInput::make('estimated_cost')
                            ->label('Chi phí dự toán')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->required()
                            ->default(0)
                            ->columnSpan(1),
                        TextInput::make('actual_cost')
                            ->label('Chi phí thực tế')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->default(0)
                            ->helperText('Cập nhật khi hoàn thành')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Trạng thái & Tiến độ')
                    ->schema([
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'pending' => 'Chờ thực hiện',
                                'in_progress' => 'Đang thực hiện',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Đã hủy',
                            ])
                            ->default('pending')
                            ->required()
                            ->live()
                            ->columnSpan(1),
                        Select::make('priority')
                            ->label('Độ ưu tiên')
                            ->options([
                                'low' => 'Thấp',
                                'normal' => 'Bình thường',
                                'high' => 'Cao',
                                'urgent' => 'Khẩn cấp',
                            ])
                            ->default('normal')
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('completed_visits')
                            ->label('Số lần đã khám')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Tự động cập nhật qua nút "Hoàn thành 1 lần khám"')
                            ->columnSpan(1),
                        TextInput::make('progress_percentage')
                            ->label('Tiến độ (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Tự động tính dựa trên số lần khám')
                            ->disabled()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('📸 Hình ảnh Before/After')
                    ->schema([
                        FileUpload::make('before_photo')
                            ->label('Ảnh Before')
                            ->image()
                            ->imageEditor()
                            ->directory('treatment-photos/items/before')
                            ->visibility('private')
                            ->maxSize(5120)
                            ->columnSpan(1),
                        FileUpload::make('after_photo')
                            ->label('Ảnh After')
                            ->image()
                            ->imageEditor()
                            ->directory('treatment-photos/items/after')
                            ->visibility('private')
                            ->maxSize(5120)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Ghi chú')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Hạng mục điều trị')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->getToothNotationDisplay()),
                TextColumn::make('service.name')
                    ->label('Dịch vụ')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('progress_percentage')
                    ->label('Tiến độ')
                    ->badge()
                    ->suffix('%')
                    ->color(fn ($record) => $record->getProgressBadgeColor())
                    ->description(fn ($record) => "{$record->completed_visits}/{$record->required_visits} lần"),
                TextColumn::make('estimated_cost')
                    ->label('Chi phí DT')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->suffix(' đ')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('actual_cost')
                    ->label('Chi phí TT')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->suffix(' đ')
                    ->alignEnd()
                    ->color(function ($record) {
                        $variance = $record->getCostVariance();
                        if ($variance > 0) return 'danger';
                        if ($variance < 0) return 'success';
                        return 'gray';
                    })
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label('Ưu tiên')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getPriorityLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'normal' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('before_photo')
                    ->label('Before')
                    ->circular()
                    ->toggleable(),
                ImageColumn::make('after_photo')
                    ->label('After')
                    ->circular()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ thực hiện',
                        'in_progress' => 'Đang thực hiện',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                    ]),
                SelectFilter::make('priority')
                    ->label('Độ ưu tiên')
                    ->options([
                        'low' => 'Thấp',
                        'normal' => 'Bình thường',
                        'high' => 'Cao',
                        'urgent' => 'Khẩn cấp',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm hạng mục')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-calculate progress
                        if (isset($data['required_visits']) && $data['required_visits'] > 0) {
                            $completed = $data['completed_visits'] ?? 0;
                            $data['progress_percentage'] = (int) (($completed / $data['required_visits']) * 100);
                        }
                        return $data;
                    })
                    ->after(function ($record) {
                        // Update parent treatment plan
                        $record->treatmentPlan->updateProgress();
                    }),
            ])
            ->recordActions([
                Action::make('complete_visit')
                    ->label('Hoàn thành 1 lần')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->completeVisit();
                    })
                    ->visible(fn ($record) => $record->completed_visits < $record->required_visits && $record->status !== 'completed'),
                Action::make('start_treatment')
                    ->label('Bắt đầu')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'in_progress',
                            'started_at' => now(),
                        ]);
                        $record->updateProgress();
                    })
                    ->visible(fn ($record) => $record->status === 'pending'),
                Action::make('complete_treatment')
                    ->label('Hoàn thành')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'progress_percentage' => 100,
                            'completed_visits' => $record->required_visits,
                            'completed_at' => now(),
                        ]);
                        $record->updateProgress();
                    })
                    ->visible(fn ($record) => $record->status !== 'completed' && $record->status !== 'cancelled'),
                EditAction::make()
                    ->label('Sửa')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-calculate progress
                        if (isset($data['required_visits']) && $data['required_visits'] > 0) {
                            $completed = $data['completed_visits'] ?? 0;
                            $data['progress_percentage'] = (int) (($completed / $data['required_visits']) * 100);
                        }
                        return $data;
                    })
                    ->after(function ($record) {
                        $record->updateProgress();
                    }),
                DeleteAction::make()
                    ->label('Xóa')
                    ->after(function ($record) {
                        // Update parent treatment plan after deletion
                        $record->treatmentPlan->updateProgress();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_in_progress')
                        ->label('Đánh dấu Đang thực hiện')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'in_progress']);
                                $record->updateProgress();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mark_completed')
                        ->label('Đánh dấu Hoàn thành')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => 'completed',
                                    'progress_percentage' => 100,
                                    'completed_visits' => $record->required_visits,
                                    'completed_at' => now(),
                                ]);
                                $record->updateProgress();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mark_cancelled')
                        ->label('Hủy bỏ')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'cancelled']);
                                $record->updateProgress();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn')
                        ->after(function () {
                            // Update parent plan after bulk delete
                            if ($this->getOwnerRecord()) {
                                $this->getOwnerRecord()->updateProgress();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('Chưa có hạng mục điều trị')
            ->emptyStateDescription('Thêm các hạng mục điều trị cụ thể vào kế hoạch.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
