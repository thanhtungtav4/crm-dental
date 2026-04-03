<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Schemas\SharedSchemas;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\PatientAppointmentActionReadModelService;
use App\Services\PatientAppointmentQuickActionService;
use App\Services\PatientAssignmentAuthorizer;
use App\Services\PatientBranchTransferService;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use App\Support\GenderBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'branch:id,name',
                'primaryDoctor:id,name',
                'ownerStaff:id,name',
            ]))
            ->columns([
                TextColumn::make('patient_code')
                    ->label('Mã bệnh nhân')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn ($record): ?string => auth()->user()?->can('view', $record)
                        ? PatientResource::getUrl('view', ['record' => $record, 'tab' => 'basic-info'])
                        : null),
                TextColumn::make('gender')
                    ->label('Giới tính')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::genderLabel($state))
                    ->icon(fn (?string $state) => GenderBadge::icon($state))
                    ->color(fn (?string $state) => GenderBadge::color($state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(query: function ($query, string $search) {
                        return $query->wherePhoneMatches($search);
                    }),
                TextColumn::make('phone_secondary')
                    ->label('Điện thoại 2')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Địa chỉ')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('medical_history')
                    ->label('Tiền sử bệnh')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái hồ sơ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::patientStatusOptions()[$state ?? ''] ?? 'Đang hoạt động')
                    ->color(fn (?string $state): string => self::patientStatusColor($state)),
                TextColumn::make('primaryDoctor.name')
                    ->label('Bác sĩ PT')
                    ->default('Chưa phân công')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ownerStaff.name')
                    ->label('NV phụ trách')
                    ->default('Chưa phân công')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('appointments_count')
                    ->label('Lịch hẹn')
                    ->counts('appointments')
                    ->badge()
                    ->color('info')
                    ->url(fn ($record): ?string => auth()->user()?->can('view', $record)
                        ? PatientResource::getUrl('view', ['record' => $record, 'tab' => 'appointments'])
                        : null),
                TextColumn::make('treatment_plans_count')
                    ->label('KHĐT')
                    ->counts('treatmentPlans')
                    ->badge()
                    ->color('success')
                    ->url(fn ($record): ?string => auth()->user()?->can('view', $record)
                        ? PatientResource::getUrl('view', ['record' => $record, 'tab' => 'exam-treatment'])
                        : null),
                TextColumn::make('invoices_count')
                    ->label('Hóa đơn')
                    ->counts('invoices')
                    ->badge()
                    ->color('warning')
                    ->url(fn ($record): ?string => auth()->user()?->can('view', $record)
                        ? PatientResource::getUrl('view', ['record' => $record, 'tab' => 'payments'])
                        : null),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('first_branch_id')
                    ->label('Chi nhánh')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query))
                    ->searchable()
                    ->preload()
                    ->indicator('Đang lọc theo chi nhánh'),
                SelectFilter::make('primary_doctor_id')
                    ->label('Bác sĩ phụ trách')
                    ->relationship('primaryDoctor', 'name', fn (Builder $query): Builder => app(PatientAssignmentAuthorizer::class)->scopeAssignableDoctors($query, auth()->user()))
                    ->searchable()
                    ->preload()
                    ->indicator('Đang lọc theo bác sĩ phụ trách'),
                SelectFilter::make('owner_staff_id')
                    ->label('Nhân viên phụ trách')
                    ->relationship('ownerStaff', 'name', fn (Builder $query): Builder => app(PatientAssignmentAuthorizer::class)->scopeAssignableStaff($query, auth()->user()))
                    ->searchable()
                    ->preload()
                    ->indicator('Đang lọc theo nhân viên phụ trách'),
                SelectFilter::make('gender')
                    ->label('Giới tính')
                    ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
                    ->indicator('Đang lọc theo giới tính'),
                SelectFilter::make('status')
                    ->label('Trạng thái hồ sơ')
                    ->options(self::patientStatusOptions())
                    ->indicator('Đang lọc theo trạng thái hồ sơ'),
            ])
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('Thu hẹp danh sách thao tác')
                    ->description('Lọc theo chi nhánh, người phụ trách và trạng thái trước khi mở hồ sơ hoặc tạo lịch hẹn.')
                    ->schema([
                        $filters['first_branch_id'],
                        $filters['primary_doctor_id'],
                        $filters['owner_staff_id'],
                        $filters['gender'],
                        $filters['status'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->splitSearchTerms(false)
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('viewAppointments')
                        ->label('Xem lịch hẹn')
                        ->icon('heroicon-o-eye')
                        ->visible(fn (Patient $record): bool => (auth()->user()?->can('Update:Appointment') ?? false)
                            && app(PatientAppointmentActionReadModelService::class)->hasActiveAppointments($record))
                        ->modalHeading('Lịch hẹn còn hiệu lực')
                        ->form([
                            SharedSchemas::activeAppointmentSelectionField(fn (Patient $record): array => app(PatientAppointmentActionReadModelService::class)
                                ->activeAppointmentOptions($record)),
                        ])
                        ->modalSubmitActionLabel('Mở')
                        ->action(function (array $data) {
                            return redirect(\App\Filament\Resources\Appointments\AppointmentResource::getUrl('edit', [
                                'record' => $data['appointment_id'],
                            ]));
                        }),
                    Action::make('createAppointment')
                        ->label('Tạo lịch hẹn')
                        ->icon('heroicon-o-calendar')
                        ->modalHeading('Tạo lịch hẹn')
                        ->visible(fn (Patient $record): bool => (auth()->user()?->can('create', Appointment::class) ?? false)
                            && (auth()->user()?->can('view', $record) ?? false))
                        ->authorize(fn (Patient $record): bool => (auth()->user()?->can('create', Appointment::class) ?? false)
                            && (auth()->user()?->can('view', $record) ?? false))
                        ->form(SharedSchemas::appointmentQuickActionFields(
                            fn ($record): ?int => is_numeric($record?->first_branch_id)
                                ? (int) $record->first_branch_id
                                : BranchAccess::defaultBranchIdForCurrentUser()
                        ))
                        ->action(function (array $data, $record) {
                            Gate::authorize('create', Appointment::class);
                            Gate::authorize('view', $record);

                            app(PatientAppointmentQuickActionService::class)->createForPatient($record, $data);
                            Notification::make()
                                ->title('Đã tạo lịch hẹn')
                                ->success()
                                ->send();
                        }),
                    Action::make('transferBranch')
                        ->label('Chuyển chi nhánh')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('warning')
                        ->visible(fn () => auth()->user()?->can(ActionPermission::PATIENT_BRANCH_TRANSFER) ?? false)
                        ->modalHeading('Chuyển bệnh nhân sang chi nhánh khác')
                        ->form([
                            \Filament\Forms\Components\Select::make('to_branch_id')
                                ->label('Chi nhánh nhận')
                                ->options(function ($record): array {
                                    $options = BranchAccess::branchOptionsForCurrentUser();

                                    if (! filled($record->first_branch_id)) {
                                        return $options;
                                    }

                                    return collect($options)
                                        ->reject(fn (string $name, int $branchId): bool => $branchId === (int) $record->first_branch_id)
                                        ->all();
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            \Filament\Forms\Components\Textarea::make('reason')
                                ->label('Lý do chuyển')
                                ->rows(2)
                                ->required(),
                            \Filament\Forms\Components\Textarea::make('note')
                                ->label('Ghi chú nội bộ')
                                ->rows(2),
                        ])
                        ->action(function (array $data, $record): void {
                            app(PatientBranchTransferService::class)->transferDirect(
                                patient: $record,
                                toBranchId: (int) $data['to_branch_id'],
                                actorId: auth()->id(),
                                reason: (string) ($data['reason'] ?? ''),
                                note: $data['note'] ?? null,
                            );

                            Notification::make()
                                ->title('Đã chuyển chi nhánh thành công')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Thao tác')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->size(Size::Small)
                    ->button(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function patientStatusOptions(): array
    {
        return [
            'active' => 'Đang hoạt động',
            'inactive' => 'Tạm ngưng',
            'blocked' => 'Khóa',
        ];
    }

    protected static function patientStatusColor(?string $status): string
    {
        return match ($status) {
            'inactive' => 'warning',
            'blocked' => 'danger',
            default => 'success',
        };
    }
}
