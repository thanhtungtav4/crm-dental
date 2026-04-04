<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Schemas\SharedSchemas;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Services\PatientAppointmentActionReadModelService;
use App\Services\PatientAppointmentQuickActionService;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable()
                    ->weight('bold')
                    ->color(fn ($record) => $record->patient ? 'primary' : null)
                    ->url(fn ($record): ?string => $record->patient && (auth()->user()?->can('view', $record->patient) ?? false)
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'basic-info'])
                        : ((auth()->user()?->can('update', $record) ?? false)
                            ? CustomerResource::getUrl('edit', ['record' => $record])
                            : null)),
                TextColumn::make('patient.patient_code')
                    ->label('Mã BN')
                    ->badge()
                    ->placeholder('Lead')
                    ->color(fn ($record): string => $record->patient ? 'success' : 'gray')
                    ->url(fn ($record): ?string => $record->patient
                        && (auth()->user()?->can('view', $record->patient) ?? false)
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'basic-info'])
                        : null),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(query: function ($query, string $search) {
                        return $query->wherePhoneMatches($search);
                    }),
                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('source_detail')
                    ->label('Nguồn chi tiết')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('customerGroup.name')
                    ->label('Nhóm KH')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('promotionGroup.name')
                    ->label('Nhóm KM')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::customerStatusLabel($state))
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Nguồn')
                    ->options(fn (): array => ClinicRuntimeSettings::customerSourceOptions()),
                SelectFilter::make('source_detail')
                    ->label('Nguồn chi tiết')
                    ->options([
                        'website' => 'Website',
                    ]),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->splitSearchTerms(false)
            ->recordActions([
                EditAction::make(),
                Action::make('viewAppointments')
                    ->label('Xem lịch hẹn')
                    ->icon('heroicon-o-eye')
                    ->visible(function (Customer $record): bool {
                        if (! $record->patient instanceof Patient || ! (auth()->user()?->can('Update:Appointment') ?? false)) {
                            return false;
                        }

                        return app(PatientAppointmentActionReadModelService::class)
                            ->hasActiveAppointments($record->patient);
                    })
                    ->modalHeading('Lịch hẹn còn hiệu lực')
                    ->form([
                        SharedSchemas::activeAppointmentSelectionField(function (Customer $record): array {
                            if (! $record->patient instanceof Patient) {
                                return [];
                            }

                            return app(PatientAppointmentActionReadModelService::class)
                                ->activeAppointmentOptions($record->patient);
                        }),
                    ])
                    ->modalSubmitActionLabel('Mở')
                    ->action(function (array $data) {
                        return redirect(\App\Filament\Resources\Appointments\AppointmentResource::getUrl('edit', [
                            'record' => $data['appointment_id'],
                        ]));
                    }),
                Action::make('convertToPatient')
                    ->label('Xác nhận thành bệnh nhân')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => blank($record->patient)
                        && (auth()->user()?->can('create', Patient::class) ?? false)
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->authorize(fn (Customer $record): bool => (auth()->user()?->can('create', Patient::class) ?? false)
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function ($record) {
                        Gate::authorize('create', Patient::class);
                        Gate::authorize('update', $record);

                        try {
                            /** @var \App\Services\PatientConversionService $service */
                            $service = app(\App\Services\PatientConversionService::class);
                            $patient = $service->convert($record);

                            $isCanonicalOwner = (int) ($patient?->customer_id ?? 0) === (int) $record->id;

                            $toast = Notification::make();

                            if ($isCanonicalOwner) {
                                $toast
                                    ->title('Đã chuyển thành bệnh nhân')
                                    ->success()
                                    ->send();
                            } else {
                                $toast
                                    ->title('Đã liên kết hồ sơ bệnh nhân hiện có')
                                    ->body("Khách hàng trùng dữ liệu voi ho so {$patient?->patient_code}. He thong se mo ngay ho so hien co.")
                                    ->warning()
                                    ->send();
                            }

                            return redirect(PatientResource::getUrl('view', [
                                'record' => $patient,
                                'tab' => 'basic-info',
                            ]));

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Không thể chuyển thành bệnh nhân')
                                ->body('Vui lòng kiểm tra dữ liệu và thử lại.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('createAppointment')
                    ->label('Tạo lịch hẹn')
                    ->icon('heroicon-o-calendar')
                    ->successNotificationTitle('Đã tạo lịch hẹn')
                    ->modalHeading('Tạo lịch hẹn')
                    ->visible(fn (Customer $record): bool => (auth()->user()?->can('create', Appointment::class) ?? false)
                        && (auth()->user()?->can('create', Patient::class) ?? false)
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->authorize(fn (Customer $record): bool => (auth()->user()?->can('create', Appointment::class) ?? false)
                        && (auth()->user()?->can('create', Patient::class) ?? false)
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->form(SharedSchemas::appointmentQuickActionFields(
                        fn ($record): ?int => is_numeric($record?->branch_id)
                            ? (int) $record->branch_id
                            : BranchAccess::defaultBranchIdForCurrentUser()
                    ))
                    ->action(function (array $data, $record) {
                        Gate::authorize('create', Appointment::class);
                        Gate::authorize('create', Patient::class);
                        Gate::authorize('update', $record);

                        app(PatientAppointmentQuickActionService::class)->createForCustomer($record, $data);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
