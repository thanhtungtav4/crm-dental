<?php

namespace App\Filament\Resources\MasterPatientDuplicates;

use App\Filament\Resources\MasterPatientDuplicates\Pages\ListMasterPatientDuplicates;
use App\Filament\Resources\MasterPatientDuplicates\Pages\ViewMasterPatientDuplicate;
use App\Filament\Resources\MasterPatientDuplicates\Schemas\MasterPatientDuplicateInfolist;
use App\Filament\Resources\MasterPatientDuplicates\Tables\MasterPatientDuplicatesTable;
use App\Models\MasterPatientDuplicate;
use App\Models\User;
use App\Services\MasterPatientMergeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MasterPatientDuplicateResource extends Resource
{
    protected static ?string $model = MasterPatientDuplicate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'identity_value';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return 'Queue MPI';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Hoạt động hàng ngày';
    }

    public static function getModelLabel(): string
    {
        return 'Ca trùng MPI';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Queue MPI';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', MasterPatientDuplicate::STATUS_OPEN)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MasterPatientDuplicateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterPatientDuplicatesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient.branch', 'branch', 'reviewer'])
            ->visibleTo(auth()->user());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMasterPatientDuplicates::route('/'),
            'view' => ViewMasterPatientDuplicate::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function mergeAction(): Action
    {
        return Action::make('mergeCase')
            ->label('Merge')
            ->icon('heroicon-o-arrows-right-left')
            ->color('danger')
            ->modalHeading('Merge hồ sơ trùng MPI')
            ->modalSubmitActionLabel('Merge hồ sơ')
            ->visible(function (MasterPatientDuplicate $record): bool {
                $authUser = auth()->user();

                return $authUser instanceof User
                    && $record->canBeMerged()
                    && $record->isReviewableBy($authUser);
            })
            ->form([
                Select::make('canonical_patient_id')
                    ->label('Hồ sơ chính')
                    ->options(fn (MasterPatientDuplicate $record): array => $record->matchedPatientOptionsForReview(auth()->user()))
                    ->default(fn (MasterPatientDuplicate $record): ?int => $record->defaultCanonicalPatientId())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Chỉ được chọn trong các hồ sơ đang nằm trong duplicate case này.'),
                Select::make('merged_patient_id')
                    ->label('Hồ sơ sẽ gộp')
                    ->options(fn (MasterPatientDuplicate $record): array => $record->matchedPatientOptionsForReview(auth()->user()))
                    ->default(fn (MasterPatientDuplicate $record): ?int => $record->defaultMergedPatientId())
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('reason')
                    ->label('Lý do merge')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->action(function (MasterPatientDuplicate $record, array $data): void {
                $candidatePatientIds = $record->matchedPatientIds();
                $canonicalPatientId = (int) ($data['canonical_patient_id'] ?? 0);
                $mergedPatientId = (int) ($data['merged_patient_id'] ?? 0);

                if (! in_array($canonicalPatientId, $candidatePatientIds, true)) {
                    throw ValidationException::withMessages([
                        'canonical_patient_id' => 'Hồ sơ chính phải thuộc duplicate case hiện tại.',
                    ]);
                }

                if (! in_array($mergedPatientId, $candidatePatientIds, true)) {
                    throw ValidationException::withMessages([
                        'merged_patient_id' => 'Hồ sơ gộp phải thuộc duplicate case hiện tại.',
                    ]);
                }

                app(MasterPatientMergeService::class)->merge(
                    canonicalPatientId: $canonicalPatientId,
                    mergedPatientId: $mergedPatientId,
                    duplicateCaseId: $record->id,
                    reason: trim((string) ($data['reason'] ?? '')),
                    actorId: auth()->id(),
                    metadata: [
                        'source' => 'filament_master_patient_duplicate_queue',
                    ],
                );

                Notification::make()
                    ->title('Đã merge hồ sơ MPI')
                    ->success()
                    ->body('Case trùng đã được resolve và audit log đã được ghi nhận.')
                    ->send();
            });
    }

    public static function ignoreAction(): Action
    {
        return Action::make('ignoreCase')
            ->label('Bỏ qua')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->modalHeading('Bỏ qua duplicate case')
            ->modalSubmitActionLabel('Xác nhận bỏ qua')
            ->requiresConfirmation()
            ->visible(function (MasterPatientDuplicate $record): bool {
                $authUser = auth()->user();

                return $authUser instanceof User
                    && $record->canBeIgnored()
                    && $record->isReviewableBy($authUser);
            })
            ->form([
                Textarea::make('note')
                    ->label('Ghi chú review')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->action(function (MasterPatientDuplicate $record, array $data): void {
                $record->markIgnored(
                    reviewedBy: auth()->id(),
                    note: trim((string) ($data['note'] ?? '')),
                );

                Notification::make()
                    ->title('Đã bỏ qua duplicate case')
                    ->success()
                    ->body('Case đã được đánh dấu ignored và lưu audit log review.')
                    ->send();
            });
    }

    public static function rollbackAction(): Action
    {
        return Action::make('rollbackLatestMerge')
            ->label('Rollback merge')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalHeading('Rollback merge MPI gần nhất')
            ->modalSubmitActionLabel('Rollback merge')
            ->requiresConfirmation()
            ->visible(function (MasterPatientDuplicate $record): bool {
                $authUser = auth()->user();

                return $authUser instanceof User
                    && $record->isReviewableBy($authUser)
                    && $record->latestAppliedMerge() !== null;
            })
            ->form([
                Textarea::make('note')
                    ->label('Ghi chú rollback')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->action(function (MasterPatientDuplicate $record, array $data): void {
                $latestAppliedMerge = $record->latestAppliedMerge();

                if ($latestAppliedMerge === null) {
                    throw ValidationException::withMessages([
                        'merge_id' => 'Không còn merge applied nào để rollback cho case này.',
                    ]);
                }

                app(MasterPatientMergeService::class)->rollback(
                    mergeId: $latestAppliedMerge->id,
                    note: trim((string) ($data['note'] ?? '')),
                    actorId: auth()->id(),
                );

                Notification::make()
                    ->title('Đã rollback merge MPI')
                    ->success()
                    ->body('Merge gần nhất đã được hoàn tác và case đã quay lại queue review.')
                    ->send();
            });
    }
}
