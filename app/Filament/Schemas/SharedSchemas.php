<?php

namespace App\Filament\Schemas;

use App\Models\Appointment;
use App\Models\Customer;
use App\Services\DoctorBranchAssignmentService;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Closure;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class SharedSchemas
{
    /**
     * Common fields for Customer/Lead profile.
     * Used in Customer create and Appointment inline create.
     */
    public static function customerProfileFields(): array
    {
        return [
            Forms\Components\TextInput::make('full_name')
                ->label('Họ và tên')
                ->required()
                ->maxLength(255)
                ->placeholder('VD: Nguyễn Văn A')
                ->columnSpan(1),

            Forms\Components\TextInput::make('phone')
                ->label('Số điện thoại')
                ->tel()
                ->maxLength(20)
                ->placeholder('VD: 0901234567')
                ->rule(function ($record): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        if (! is_string($value) || trim($value) === '') {
                            return;
                        }

                        $exists = Customer::query()
                            ->when($record?->id, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot((int) $record->id))
                            ->wherePhoneMatches($value)
                            ->exists();

                        if ($exists) {
                            $fail('Số điện thoại này đã tồn tại.');
                        }
                    };
                })
                ->columnSpan(1),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->placeholder('VD: email@example.com')
                ->columnSpan(1),

            Forms\Components\DatePicker::make('birthday')
                ->label('Ngày sinh')
                ->maxDate(now())
                ->native(false)
                ->displayFormat('d/m/Y')
                ->columnSpan(1),

            Forms\Components\Select::make('gender')
                ->label('Giới tính')
                ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
                ->default('male')
                ->columnSpan(1),

            Forms\Components\Textarea::make('address')
                ->label('Địa chỉ')
                ->rows(2)
                ->columnSpanFull()
                ->placeholder('Nhập địa chỉ chi tiết...'),
        ];
    }

    public static function appointmentQuickActionFields(Closure $resolveDefaultBranchId): array
    {
        return [
            Forms\Components\Select::make('doctor_id')
                ->label('Bác sĩ')
                ->options(function (callable $get): array {
                    $branchId = $get('branch_id');
                    $date = $get('date');
                    $appointmentAt = filled($date) ? \Carbon\Carbon::parse((string) $date) : null;

                    return app(DoctorBranchAssignmentService::class)
                        ->doctorOptionsForBranch(
                            branchId: filled($branchId) ? (int) $branchId : null,
                            at: $appointmentAt,
                        );
                })
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Chỉ hiển thị bác sĩ đang được phân công ở chi nhánh đã chọn.'),
            Forms\Components\Select::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => BranchAccess::branchOptionsForCurrentUser())
                ->searchable()
                ->preload()
                ->default($resolveDefaultBranchId)
                ->live()
                ->afterStateUpdated(function (callable $get, callable $set, $state): void {
                    $doctorId = $get('doctor_id');

                    if (! filled($doctorId) || ! filled($state)) {
                        return;
                    }

                    $date = $get('date');
                    $appointmentAt = filled($date) ? \Carbon\Carbon::parse((string) $date) : null;

                    $isAllowed = app(DoctorBranchAssignmentService::class)->isDoctorAssignedToBranch(
                        doctorId: (int) $doctorId,
                        branchId: (int) $state,
                        at: $appointmentAt,
                    );

                    if (! $isAllowed) {
                        $set('doctor_id', null);
                    }
                })
                ->required(),
            Forms\Components\DateTimePicker::make('date')
                ->label('Thời gian')
                ->default(fn () => now()->addDay())
                ->live()
                ->afterStateUpdated(function (callable $get, callable $set, $state): void {
                    $doctorId = $get('doctor_id');
                    $branchId = $get('branch_id');

                    if (! filled($doctorId) || ! filled($branchId) || ! filled($state)) {
                        return;
                    }

                    $isAllowed = app(DoctorBranchAssignmentService::class)->isDoctorAssignedToBranch(
                        doctorId: (int) $doctorId,
                        branchId: (int) $branchId,
                        at: \Carbon\Carbon::parse((string) $state),
                    );

                    if (! $isAllowed) {
                        $set('doctor_id', null);
                    }
                })
                ->required(),
            Forms\Components\Select::make('appointment_kind')
                ->label('Loại lịch hẹn')
                ->options([
                    'booking' => 'Đặt hẹn',
                    're_exam' => 'Tái khám',
                ])
                ->default('booking')
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->options(Appointment::statusOptions())
                ->default(Appointment::STATUS_SCHEDULED),
            Forms\Components\Textarea::make('cancellation_reason')
                ->label('Lý do hủy')
                ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                ->required(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                ->rows(2),
            Forms\Components\Textarea::make('reschedule_reason')
                ->label('Lý do hẹn lại')
                ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                ->required(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                ->rows(2),
            Forms\Components\Textarea::make('note')
                ->label('Ghi chú')
                ->rows(3),
        ];
    }

    public static function activeAppointmentSelectionField(Closure $resolveOptions): Forms\Components\Select
    {
        return Forms\Components\Select::make('appointment_id')
            ->label('Chọn lịch hẹn')
            ->options($resolveOptions)
            ->required();
    }
}
