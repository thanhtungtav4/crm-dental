<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                BadgeColumn::make('entity_type')
                    ->label('Entity')
                    ->colors([
                        'primary' => AuditLog::ENTITY_PAYMENT,
                        'warning' => AuditLog::ENTITY_INVOICE,
                        'info' => [AuditLog::ENTITY_APPOINTMENT, AuditLog::ENTITY_MASTER_PATIENT_INDEX, AuditLog::ENTITY_PLAN_ITEM],
                        'success' => AuditLog::ENTITY_CARE_TICKET,
                        'gray' => [AuditLog::ENTITY_AUTOMATION, AuditLog::ENTITY_REPORT_SNAPSHOT],
                        'danger' => AuditLog::ENTITY_MASTER_DATA_SYNC,
                    ])
                    ->sortable(),
                TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->sortable(),
                BadgeColumn::make('action')
                    ->label('Hành động')
                    ->colors([
                        'info' => [AuditLog::ACTION_CREATE, AuditLog::ACTION_FOLLOW_UP, AuditLog::ACTION_SNAPSHOT],
                        'warning' => [AuditLog::ACTION_UPDATE, AuditLog::ACTION_RESCHEDULE, AuditLog::ACTION_RUN],
                        'danger' => [AuditLog::ACTION_REFUND, AuditLog::ACTION_FAIL],
                        'primary' => [AuditLog::ACTION_REVERSAL, AuditLog::ACTION_APPROVE],
                        'gray' => [AuditLog::ACTION_CANCEL, AuditLog::ACTION_NO_SHOW, AuditLog::ACTION_SYNC, AuditLog::ACTION_DEDUPE, AuditLog::ACTION_SLA_CHECK],
                        'success' => AuditLog::ACTION_COMPLETE,
                    ]),
                TextColumn::make('actor.name')
                    ->label('Người thực hiện')
                    ->searchable(),
                TextColumn::make('metadata')
                    ->label('Metadata')
                    ->limit(50)
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->label('Entity')
                    ->options([
                        AuditLog::ENTITY_PAYMENT => 'Payment',
                        AuditLog::ENTITY_INVOICE => 'Invoice',
                        AuditLog::ENTITY_APPOINTMENT => 'Appointment',
                        AuditLog::ENTITY_CARE_TICKET => 'Care Ticket',
                        AuditLog::ENTITY_PLAN_ITEM => 'Plan Item',
                        AuditLog::ENTITY_MASTER_DATA_SYNC => 'Master Data Sync',
                        AuditLog::ENTITY_MASTER_PATIENT_INDEX => 'MPI',
                        AuditLog::ENTITY_REPORT_SNAPSHOT => 'Report Snapshot',
                        AuditLog::ENTITY_AUTOMATION => 'Automation',
                    ]),
                SelectFilter::make('action')
                    ->label('Hành động')
                    ->options([
                        AuditLog::ACTION_CREATE => 'Create',
                        AuditLog::ACTION_UPDATE => 'Update',
                        AuditLog::ACTION_REFUND => 'Refund',
                        AuditLog::ACTION_REVERSAL => 'Reversal',
                        AuditLog::ACTION_CANCEL => 'Cancel',
                        AuditLog::ACTION_RESCHEDULE => 'Reschedule',
                        AuditLog::ACTION_NO_SHOW => 'No show',
                        AuditLog::ACTION_COMPLETE => 'Complete',
                        AuditLog::ACTION_FOLLOW_UP => 'Follow up',
                        AuditLog::ACTION_FAIL => 'Fail',
                        AuditLog::ACTION_SYNC => 'Sync',
                        AuditLog::ACTION_SNAPSHOT => 'Snapshot',
                        AuditLog::ACTION_SLA_CHECK => 'SLA Check',
                        AuditLog::ACTION_DEDUPE => 'Dedupe',
                        AuditLog::ACTION_RUN => 'Run',
                        AuditLog::ACTION_APPROVE => 'Approve',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
