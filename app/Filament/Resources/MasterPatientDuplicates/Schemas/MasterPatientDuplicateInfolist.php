<?php

namespace App\Filament\Resources\MasterPatientDuplicates\Schemas;

use App\Models\MasterPatientDuplicate;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MasterPatientDuplicateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tổng quan duplicate case')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => MasterPatientDuplicate::statusLabel($state))
                            ->color(fn (?string $state): string => MasterPatientDuplicate::statusColor($state)),
                        TextEntry::make('identity_type')
                            ->label('Loại định danh')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => MasterPatientDuplicate::identityTypeLabel($state))
                            ->color('primary'),
                        TextEntry::make('confidence_score')
                            ->label('Độ tin cậy')
                            ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 0).'%'),
                        TextEntry::make('identity_value')
                            ->label('Giá trị khớp')
                            ->copyable()
                            ->columnSpan(2),
                        TextEntry::make('branch.name')
                            ->label('Chi nhánh case')
                            ->placeholder('-'),
                        TextEntry::make('metadata.patient_count')
                            ->label('Số hồ sơ')
                            ->formatStateUsing(fn (MasterPatientDuplicate $record): string => (string) count($record->matchedPatientIds())),
                        TextEntry::make('metadata.branch_count')
                            ->label('Số chi nhánh')
                            ->formatStateUsing(fn (MasterPatientDuplicate $record): string => (string) count($record->matchedBranchIds())),
                        TextEntry::make('reviewer.name')
                            ->label('Reviewer')
                            ->placeholder('-'),
                        TextEntry::make('reviewed_at')
                            ->label('Reviewed lúc')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('review_note')
                            ->label('Ghi chú review')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('review_scope')
                            ->label('Khả năng thao tác')
                            ->state(function (MasterPatientDuplicate $record): string {
                                $authUser = auth()->user();

                                if ($authUser instanceof User && $record->isReviewableBy($authUser)) {
                                    return 'Bạn có thể merge, ignore hoặc rollback case này.';
                                }

                                return 'Case này đang chứa chi nhánh ngoài phạm vi của bạn. Bạn chỉ xem được tóm tắt.';
                            })
                            ->badge()
                            ->color(function (MasterPatientDuplicate $record): string {
                                $authUser = auth()->user();

                                return $authUser instanceof User && $record->isReviewableBy($authUser)
                                    ? 'success'
                                    : 'warning';
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Các hồ sơ đang khớp')
                    ->schema([
                        RepeatableEntry::make('matched_patients_preview')
                            ->label('')
                            ->state(fn (MasterPatientDuplicate $record): array => $record->matchedPatientsForReview(auth()->user()))
                            ->schema([
                                TextEntry::make('patient_code')
                                    ->label('Mã BN')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('full_name')
                                    ->label('Bệnh nhân'),
                                TextEntry::make('branch_name')
                                    ->label('Chi nhánh'),
                                TextEntry::make('status')
                                    ->label('Trạng thái')
                                    ->badge(),
                                TextEntry::make('phone')
                                    ->label('Điện thoại')
                                    ->placeholder('-'),
                                TextEntry::make('email')
                                    ->label('Email')
                                    ->placeholder('-'),
                            ])
                            ->grid(2),
                    ]),

                Section::make('Lịch sử merge liên quan')
                    ->schema([
                        RepeatableEntry::make('merge_history_preview')
                            ->label('')
                            ->state(fn (MasterPatientDuplicate $record): array => $record->mergeHistoryForReview(auth()->user()))
                            ->schema([
                                TextEntry::make('merge_id')
                                    ->label('Merge ID')
                                    ->formatStateUsing(fn (mixed $state): string => '#'.(string) $state)
                                    ->badge(),
                                TextEntry::make('status')
                                    ->label('Trạng thái merge')
                                    ->badge(),
                                TextEntry::make('canonical_patient')
                                    ->label('Hồ sơ chính'),
                                TextEntry::make('merged_patient')
                                    ->label('Hồ sơ gộp'),
                                TextEntry::make('merged_by')
                                    ->label('Thực hiện bởi'),
                                TextEntry::make('merged_at')
                                    ->label('Merge lúc')
                                    ->placeholder('-'),
                                TextEntry::make('rolled_back_at')
                                    ->label('Rollback lúc')
                                    ->placeholder('-'),
                                TextEntry::make('rollback_note')
                                    ->label('Ghi chú rollback')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ])
                            ->grid(2),
                    ])
                    ->visible(fn (MasterPatientDuplicate $record): bool => $record->merges()->exists()),
            ]);
    }
}
