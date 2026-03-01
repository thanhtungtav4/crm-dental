<?php

namespace App\Filament\Resources\TreatmentSessions\Tables;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use App\Models\TreatmentSession;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreatmentSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'treatmentPlan.patient:id,full_name,patient_code',
                'treatmentPlan:id,patient_id,title,status',
                'doctor:id,name',
                'assistant:id,name',
            ]))
            ->columns([
                TextColumn::make('treatmentPlan.patient.full_name')
                    ->label('Bệnh nhân')
                    ->description(fn (TreatmentSession $record): ?string => $record->treatmentPlan?->patient?->patient_code
                        ? 'Mã BN: '.$record->treatmentPlan->patient->patient_code
                        : null)
                    ->url(fn (TreatmentSession $record): ?string => $record->treatmentPlan?->patient
                        ? PatientResource::getUrl('view', ['record' => $record->treatmentPlan->patient, 'tab' => 'exam-treatment'])
                        : null)
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('treatmentPlan.title')
                    ->label('Kế hoạch')
                    ->description(fn (TreatmentSession $record): ?string => $record->treatmentPlan?->status
                        ? 'Trạng thái: '.$record->treatmentPlan->getStatusLabel()
                        : null)
                    ->url(fn (TreatmentSession $record): ?string => $record->treatmentPlan
                        ? TreatmentPlanResource::getUrl('edit', [
                            'record' => $record->treatmentPlan,
                            'return_url' => request()->fullUrl(),
                        ])
                        : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->toggleable(),
                TextColumn::make('assistant.name')
                    ->label('Trợ thủ')
                    ->toggleable(),
                TextColumn::make('performed_at')
                    ->label('Thời gian')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('view_patient_profile')
                    ->label('Hồ sơ BN')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->url(fn (TreatmentSession $record): ?string => $record->treatmentPlan?->patient
                        ? PatientResource::getUrl('view', ['record' => $record->treatmentPlan->patient, 'tab' => 'exam-treatment'])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (TreatmentSession $record): bool => $record->treatmentPlan?->patient !== null),
                Action::make('view_treatment_plan')
                    ->label('Mở kế hoạch')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->url(fn (TreatmentSession $record): ?string => $record->treatmentPlan
                        ? TreatmentPlanResource::getUrl('edit', [
                            'record' => $record->treatmentPlan,
                            'return_url' => request()->fullUrl(),
                        ])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (TreatmentSession $record): bool => $record->treatmentPlan !== null),
                EditAction::make()
                    ->url(fn (TreatmentSession $record): string => TreatmentSessionResource::getUrl('edit', [
                        'record' => $record,
                        'return_url' => request()->fullUrl(),
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
