<?php

namespace App\Filament\Resources\TreatmentPlans\RelationManagers;

use App\Models\PlanItem;
use App\Services\PlanItemWorkflowService;
use App\Services\TreatmentDeletionGuardService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
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
                        Select::make('approval_status')
                            ->label('Phê duyệt bệnh nhân')
                            ->options(PlanItem::approvalStatusOptions())
                            ->default(PlanItem::APPROVAL_PROPOSED)
                            ->required()
                            ->live()
                            ->columnSpan(1),
                        Textarea::make('approval_decline_reason')
                            ->label('Lý do từ chối')
                            ->rows(2)
                            ->visible(fn (callable $get): bool => $get('approval_status') === PlanItem::APPROVAL_DECLINED)
                            ->required(fn (callable $get): bool => $get('approval_status') === PlanItem::APPROVAL_DECLINED)
                            ->columnSpan(1),
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                PlanItem::STATUS_PENDING => 'Chờ thực hiện',
                                PlanItem::STATUS_IN_PROGRESS => 'Đang thực hiện',
                                PlanItem::STATUS_COMPLETED => 'Hoàn thành',
                                PlanItem::STATUS_CANCELLED => 'Đã hủy',
                            ])
                            ->default(PlanItem::STATUS_PENDING)
                            ->required()
                            ->disabled(fn (?PlanItem $record): bool => $record !== null)
                            ->dehydrated(fn (?PlanItem $record): bool => $record === null)
                            ->helperText('Sau khi tạo, dùng các action bên dưới để đổi trạng thái và giữ audit workflow nhất quán.')
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
                TextColumn::make('approval_status')
                    ->label('Duyệt KH')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getApprovalStatusLabel())
                    ->color(fn ($record): string => $record->getApprovalStatusBadgeColor()),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        PlanItem::STATUS_PENDING => 'gray',
                        PlanItem::STATUS_IN_PROGRESS => 'warning',
                        PlanItem::STATUS_COMPLETED => 'success',
                        PlanItem::STATUS_CANCELLED => 'danger',
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
                        if ($variance > 0) {
                            return 'danger';
                        }
                        if ($variance < 0) {
                            return 'success';
                        }

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
                TextColumn::make('approval_decline_reason')
                    ->label('Lý do từ chối')
                    ->placeholder('-')
                    ->limit(80)
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
                SelectFilter::make('approval_status')
                    ->label('Phê duyệt bệnh nhân')
                    ->options(PlanItem::approvalStatusOptions()),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        PlanItem::STATUS_PENDING => 'Chờ thực hiện',
                        PlanItem::STATUS_IN_PROGRESS => 'Đang thực hiện',
                        PlanItem::STATUS_COMPLETED => 'Hoàn thành',
                        PlanItem::STATUS_CANCELLED => 'Đã hủy',
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
                    ->successNotificationTitle('Đã thêm hạng mục điều trị')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->sanitizePlanItemPayload($data);
                    })
                    ->after(function ($record) {
                        // Update parent treatment plan
                        $record->treatmentPlan->updateProgress();
                    }),
            ])
            ->recordActions([
                Action::make('propose_for_patient')
                    ->label('Gửi đề xuất')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->successNotificationTitle('Đã gửi đề xuất cho bệnh nhân')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'approval_status' => PlanItem::APPROVAL_PROPOSED,
                            'approval_decline_reason' => null,
                            'status' => PlanItem::STATUS_PENDING,
                            'completed_visits' => 0,
                            'progress_percentage' => 0,
                            'started_at' => null,
                            'completed_at' => null,
                            'is_completed' => false,
                        ]);
                        $record->treatmentPlan?->updateProgress();
                    })
                    ->visible(fn ($record) => in_array($record->approval_status, [
                        PlanItem::APPROVAL_DRAFT,
                        PlanItem::APPROVAL_DECLINED,
                    ], true)),
                Action::make('approve_by_patient')
                    ->label('KH đồng ý')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->successNotificationTitle('Đã xác nhận bệnh nhân đồng ý')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'approval_status' => PlanItem::APPROVAL_APPROVED,
                            'approval_decline_reason' => null,
                        ]);
                        $record->treatmentPlan?->updateProgress();
                    })
                    ->visible(fn ($record) => in_array($record->approval_status, [
                        PlanItem::APPROVAL_PROPOSED,
                        PlanItem::APPROVAL_DECLINED,
                    ], true)),
                Action::make('decline_by_patient')
                    ->label('KH từ chối')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->successNotificationTitle('Đã ghi nhận bệnh nhân từ chối')
                    ->form([
                        Textarea::make('approval_decline_reason')
                            ->label('Lý do từ chối')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'approval_status' => PlanItem::APPROVAL_DECLINED,
                            'approval_decline_reason' => trim((string) ($data['approval_decline_reason'] ?? '')),
                            'status' => PlanItem::STATUS_PENDING,
                            'completed_visits' => 0,
                            'progress_percentage' => 0,
                            'started_at' => null,
                            'completed_at' => null,
                            'is_completed' => false,
                        ]);
                        $record->treatmentPlan?->updateProgress();
                    })
                    ->visible(fn ($record) => in_array($record->approval_status, [
                        PlanItem::APPROVAL_DRAFT,
                        PlanItem::APPROVAL_PROPOSED,
                    ], true)),
                Action::make('complete_visit')
                    ->label('Hoàn thành 1 lần')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->successNotificationTitle('Đã hoàn thành một lần điều trị')
                    ->form([
                        Textarea::make('reason')
                            ->label('Ghi chú tiến độ')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(PlanItemWorkflowService::class)->completeVisit(
                            planItem: $record,
                            reason: $data['reason'] ?? null,
                        );
                    })
                    ->visible(fn ($record) => $record->canStartTreatment()
                        && $record->completed_visits < $record->required_visits
                        && $record->status !== PlanItem::STATUS_COMPLETED
                        && $record->status !== PlanItem::STATUS_CANCELLED),
                Action::make('start_treatment')
                    ->label('Bắt đầu')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->successNotificationTitle('Đã bắt đầu hạng mục điều trị')
                    ->form([
                        Textarea::make('reason')
                            ->label('Ghi chú vận hành')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(PlanItemWorkflowService::class)->startTreatment(
                            planItem: $record,
                            reason: $data['reason'] ?? null,
                        );
                    })
                    ->visible(fn ($record) => $record->canStartTreatment()
                        && $record->status === PlanItem::STATUS_PENDING),
                Action::make('complete_treatment')
                    ->label('Hoàn thành')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->successNotificationTitle('Đã hoàn thành hạng mục điều trị')
                    ->form([
                        Textarea::make('reason')
                            ->label('Ghi chú hoàn thành')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(PlanItemWorkflowService::class)->completeTreatment(
                            planItem: $record,
                            reason: $data['reason'] ?? null,
                        );
                    })
                    ->visible(fn ($record) => $record->canStartTreatment()
                        && $record->status !== PlanItem::STATUS_COMPLETED
                        && $record->status !== PlanItem::STATUS_CANCELLED),
                Action::make('cancel_treatment')
                    ->label('Hủy')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->successNotificationTitle('Đã hủy hạng mục điều trị')
                    ->form([
                        Textarea::make('reason')
                            ->label('Lý do hủy')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        app(PlanItemWorkflowService::class)->cancel(
                            planItem: $record,
                            reason: $data['reason'] ?? null,
                        );
                    })
                    ->visible(fn ($record) => in_array($record->status, [
                        PlanItem::STATUS_PENDING,
                        PlanItem::STATUS_IN_PROGRESS,
                    ], true)),
                EditAction::make()
                    ->label('Sửa')
                    ->successNotificationTitle('Đã cập nhật hạng mục điều trị')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->sanitizePlanItemPayload($data);
                    })
                    ->after(function ($record) {
                        $record->updateProgress();
                    }),
                DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn ($record): bool => app(TreatmentDeletionGuardService::class)->canDeletePlanItem($record))
                    ->successNotificationTitle('Đã xóa hạng mục điều trị')
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
                        ->successNotificationTitle('Đã cập nhật hạng mục sang đang thực hiện')
                        ->form([
                            Textarea::make('reason')
                                ->label('Ghi chú vận hành')
                                ->rows(3),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record->canStartTreatment() || $record->status !== PlanItem::STATUS_PENDING) {
                                    $skipped++;

                                    continue;
                                }

                                app(PlanItemWorkflowService::class)->startTreatment(
                                    planItem: $record,
                                    reason: $data['reason'] ?? null,
                                );
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("Đã bỏ qua {$skipped} hạng mục chưa được bệnh nhân duyệt")
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mark_completed')
                        ->label('Đánh dấu Hoàn thành')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->successNotificationTitle('Đã cập nhật hạng mục sang hoàn thành')
                        ->form([
                            Textarea::make('reason')
                                ->label('Ghi chú hoàn thành')
                                ->rows(3),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record->canStartTreatment() || in_array($record->status, [
                                    PlanItem::STATUS_COMPLETED,
                                    PlanItem::STATUS_CANCELLED,
                                ], true)) {
                                    $skipped++;

                                    continue;
                                }

                                app(PlanItemWorkflowService::class)->completeTreatment(
                                    planItem: $record,
                                    reason: $data['reason'] ?? null,
                                );
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("Đã bỏ qua {$skipped} hạng mục chưa được bệnh nhân duyệt")
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('mark_cancelled')
                        ->label('Hủy bỏ')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->successNotificationTitle('Đã hủy các hạng mục đã chọn')
                        ->form([
                            Textarea::make('reason')
                                ->label('Lý do hủy')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! in_array($record->status, [
                                    PlanItem::STATUS_PENDING,
                                    PlanItem::STATUS_IN_PROGRESS,
                                ], true)) {
                                    $skipped++;

                                    continue;
                                }

                                app(PlanItemWorkflowService::class)->cancel(
                                    planItem: $record,
                                    reason: $data['reason'] ?? null,
                                );
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("Đã bỏ qua {$skipped} hạng mục không còn ở trạng thái có thể hủy")
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('Chưa có hạng mục điều trị')
            ->emptyStateDescription('Thêm các hạng mục điều trị cụ thể vào kế hoạch.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    protected function sanitizePlanItemPayload(array $data): array
    {
        $requiredVisits = max(1, (int) ($data['required_visits'] ?? 1));
        $completedVisits = max(0, min((int) ($data['completed_visits'] ?? 0), $requiredVisits));

        $approvalStatus = PlanItem::normalizeApprovalStatus($data['approval_status'] ?? null)
            ?? PlanItem::DEFAULT_APPROVAL_STATUS;

        $status = PlanItem::normalizeStatus($data['status'] ?? null) ?? PlanItem::STATUS_PENDING;

        if ($approvalStatus !== PlanItem::APPROVAL_APPROVED) {
            $status = PlanItem::STATUS_PENDING;
            $completedVisits = 0;
            $data['started_at'] = null;
            $data['completed_at'] = null;
        }

        if ($status === PlanItem::STATUS_COMPLETED) {
            $completedVisits = $requiredVisits;
        }

        $data['required_visits'] = $requiredVisits;
        $data['completed_visits'] = $completedVisits;
        $data['status'] = $status;
        $data['progress_percentage'] = (int) (($completedVisits / $requiredVisits) * 100);
        $data['approval_status'] = $approvalStatus;
        $data['approval_decline_reason'] = $approvalStatus === PlanItem::APPROVAL_DECLINED
            ? trim((string) ($data['approval_decline_reason'] ?? ''))
            : null;
        $data['patient_approved'] = $approvalStatus === PlanItem::APPROVAL_APPROVED;

        return $data;
    }
}
