<?php

namespace App\Filament\Resources\MasterPatientDuplicates\Tables;

use App\Filament\Resources\MasterPatientDuplicates\MasterPatientDuplicateResource;
use App\Models\MasterPatientDuplicate;
use App\Support\BranchAccess;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MasterPatientDuplicatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => MasterPatientDuplicate::statusLabel($state))
                    ->color(fn (?string $state): string => MasterPatientDuplicate::statusColor($state))
                    ->sortable(),
                TextColumn::make('identity_type')
                    ->label('Định danh')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => MasterPatientDuplicate::identityTypeLabel($state))
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('identity_value')
                    ->label('Giá trị khớp')
                    ->searchable()
                    ->copyable()
                    ->limit(40)
                    ->weight('bold'),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh case')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('metadata.patient_count')
                    ->label('Số hồ sơ')
                    ->badge()
                    ->getStateUsing(fn (MasterPatientDuplicate $record): int => count($record->matchedPatientIds()))
                    ->color('info'),
                TextColumn::make('metadata.branch_count')
                    ->label('Số chi nhánh')
                    ->badge()
                    ->getStateUsing(fn (MasterPatientDuplicate $record): int => count($record->matchedBranchIds()))
                    ->color('warning'),
                TextColumn::make('confidence_score')
                    ->label('Độ tin cậy')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 0).'%')
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('review_note')
                    ->label('Ghi chú review')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(MasterPatientDuplicate::statusOptions()),
                SelectFilter::make('identity_type')
                    ->label('Loại định danh')
                    ->options(MasterPatientDuplicate::identityTypeOptions()),
                SelectFilter::make('branch_id')
                    ->label('Chi nhánh case')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query, false),
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('confidence_score', 'desc')
            ->recordActions([
                ViewAction::make(),
                MasterPatientDuplicateResource::mergeAction(),
                MasterPatientDuplicateResource::ignoreAction(),
                MasterPatientDuplicateResource::rollbackAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Chưa có duplicate case MPI')
            ->emptyStateDescription('Khi phát hiện trùng định danh liên chi nhánh, queue review sẽ xuất hiện tại đây.');
    }
}
