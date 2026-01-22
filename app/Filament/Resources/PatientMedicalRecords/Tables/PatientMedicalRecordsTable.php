<?php

namespace App\Filament\Resources\PatientMedicalRecords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PatientMedicalRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.patient_code')
                    ->label('Mã BN')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->patient?->phone),
                TextColumn::make('allergies_display')
                    ->label('🚨 Dị ứng')
                    ->getStateUsing(function ($record) {
                        if (!$record->hasAllergies()) {
                            return 'Không';
                        }
                        $count = count($record->allergies);
                        return implode(', ', array_slice($record->allergies, 0, 2)) . 
                               ($count > 2 ? " (+".($count-2)." khác)" : '');
                    })
                    ->badge()
                    ->color(fn ($record) => $record->hasAllergies() ? 'danger' : 'success')
                    ->icon(fn ($record) => $record->hasAllergies() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->weight(fn ($record) => $record->hasAllergies() ? FontWeight::Bold : FontWeight::Regular),
                TextColumn::make('chronic_diseases_display')
                    ->label('Bệnh mãn tính')
                    ->getStateUsing(function ($record) {
                        if (!$record->hasChronicDiseases()) {
                            return 'Không';
                        }
                        $count = count($record->chronic_diseases);
                        return implode(', ', array_slice($record->chronic_diseases, 0, 2)) . 
                               ($count > 2 ? " (+".($count-2).")" : '');
                    })
                    ->badge()
                    ->color(fn ($record) => $record->hasChronicDiseases() ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('blood_type')
                    ->label('Nhóm máu')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => $state === 'unknown' ? 'Chưa xác định' : $state)
                    ->sortable(),
                TextColumn::make('insurance_provider')
                    ->label('Bảo hiểm')
                    ->searchable()
                    ->placeholder('Không có')
                    ->toggleable(),
                TextColumn::make('insurance_expiry_date')
                    ->label('BH hết hạn')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->isInsuranceExpired() ? 'danger' : null)
                    ->icon(fn ($record) => $record->isInsuranceExpired() ? 'heroicon-o-exclamation-circle' : null)
                    ->placeholder('Không có')
                    ->toggleable(),
                TextColumn::make('emergency_contact_name')
                    ->label('Liên hệ khẩn cấp')
                    ->searchable()
                    ->description(fn ($record) => $record->emergency_contact_phone)
                    ->placeholder('Chưa có')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updatedBy.name')
                    ->label('Cập nhật bởi')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Cập nhật lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('has_allergies')
                    ->label('Có dị ứng')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có dị ứng')
                    ->falseLabel('Không dị ứng')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('allergies')->whereRaw('JSON_LENGTH(allergies) > 0'),
                        false: fn ($query) => $query->where(function ($q) {
                            $q->whereNull('allergies')->orWhereRaw('JSON_LENGTH(allergies) = 0');
                        }),
                    ),
                TernaryFilter::make('has_chronic_diseases')
                    ->label('Có bệnh mãn tính')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có bệnh')
                    ->falseLabel('Không bệnh')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('chronic_diseases')->whereRaw('JSON_LENGTH(chronic_diseases) > 0'),
                        false: fn ($query) => $query->where(function ($q) {
                            $q->whereNull('chronic_diseases')->orWhereRaw('JSON_LENGTH(chronic_diseases) = 0');
                        }),
                    ),
                SelectFilter::make('blood_type')
                    ->label('Nhóm máu')
                    ->options([
                        'A+' => 'A+',
                        'A-' => 'A-',
                        'B+' => 'B+',
                        'B-' => 'B-',
                        'AB+' => 'AB+',
                        'AB-' => 'AB-',
                        'O+' => 'O+',
                        'O-' => 'O-',
                        'unknown' => 'Chưa xác định',
                    ]),
                TernaryFilter::make('has_insurance')
                    ->label('Bảo hiểm')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có bảo hiểm')
                    ->falseLabel('Không bảo hiểm')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('insurance_provider'),
                        false: fn ($query) => $query->whereNull('insurance_provider'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
