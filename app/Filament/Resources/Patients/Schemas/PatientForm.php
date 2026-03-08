<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Models\Patient;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema(static::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('customer_id')
                ->relationship(
                    name: 'customer',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: function (Builder $query): Builder {
                        $query->doesntHave('patient');
                        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');

                        return $query;
                    },
                )
                ->label('Khách hàng')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function ($state, $set) {
                    if (! $state) {
                        return;
                    }
                    $customer = \App\Models\Customer::find($state);
                    if (! $customer) {
                        return;
                    }
                    // Auto-fill patient name & phone from customer
                    if ($customer->full_name) {
                        $set('full_name', $customer->full_name);
                    }
                    if ($customer->phone) {
                        $set('phone', $customer->phone);
                    }
                    // Auto-fill email (editable field)
                    if ($customer->email) {
                        $set('email', $customer->email);
                    }
                    if ($customer->customer_group_id) {
                        $set('customer_group_id', $customer->customer_group_id);
                    }
                    if ($customer->promotion_group_id) {
                        $set('promotion_group_id', $customer->promotion_group_id);
                    }
                })
                ->visibleOn('create')
                ->nullable(),

            Forms\Components\Placeholder::make('customer_readonly')
                ->label('Khách hàng')
                ->content(fn ($record) => $record?->customer?->full_name)
                ->visibleOn('edit')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('patient_code')
                ->label('Mã bệnh nhân')
                ->helperText('Tự động sinh — không thể chỉnh sửa')
                ->maxLength(50)
                ->unique(table: 'patients', column: 'patient_code', ignoreRecord: true)
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit')
                ->nullable(),

            Forms\Components\Select::make('first_branch_id')
                ->relationship(
                    name: 'branch',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                )
                ->label('Chi nhánh')
                ->searchable()
                ->preload()
                ->default(fn (): ?int => BranchAccess::defaultBranchIdForCurrentUser())
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                    $branchId = filled($state) ? (int) $state : null;
                    $authorizer = app(PatientAssignmentAuthorizer::class);

                    $ownerStaffId = $get('owner_staff_id');
                    if (filled($ownerStaffId)) {
                        $ownerStillAllowed = $authorizer
                            ->scopeAssignableStaff(
                                query: \App\Models\User::query()->whereKey((int) $ownerStaffId),
                                actor: auth()->user(),
                                branchId: $branchId,
                            )
                            ->exists();

                        if (! $ownerStillAllowed) {
                            $set('owner_staff_id', null);
                        }
                    }

                    $doctorId = $get('primary_doctor_id');
                    if (filled($doctorId)) {
                        $doctorStillAllowed = $authorizer
                            ->scopeAssignableDoctors(
                                query: \App\Models\User::query()->whereKey((int) $doctorId),
                                actor: auth()->user(),
                                branchId: $branchId,
                            )
                            ->exists();

                        if (! $doctorStillAllowed) {
                            $set('primary_doctor_id', null);
                        }
                    }
                })
                ->nullable(),

            Forms\Components\TextInput::make('full_name')
                ->label('Họ và tên')
                ->required()
                ->maxLength(255),

            Forms\Components\DatePicker::make('birthday')
                ->label('Ngày sinh')
                ->nullable(),

            Forms\Components\TextInput::make('cccd')
                ->label('Số CCCD')
                ->maxLength(20)
                ->nullable(),

            Forms\Components\Select::make('gender')
                ->label('Giới tính')
                ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
                ->nullable(),

            Forms\Components\TextInput::make('phone')
                ->label('Điện thoại')
                ->tel()
                ->maxLength(20)
                ->rule(function (callable $get, $record): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                        if (! is_string($value) || trim($value) === '') {
                            return;
                        }

                        $phoneHash = Patient::phoneSearchHash($value);
                        if ($phoneHash === null) {
                            return;
                        }

                        $branchId = $get('first_branch_id');

                        $exists = Patient::withTrashed()
                            ->when($record?->id, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot((int) $record->id))
                            ->where('phone_search_hash', $phoneHash)
                            ->when(
                                $branchId,
                                fn (EloquentBuilder $query): EloquentBuilder => $query->where('first_branch_id', $branchId),
                                fn (EloquentBuilder $query): EloquentBuilder => $query->whereNull('first_branch_id')
                            )
                            ->exists();

                        if ($exists) {
                            $fail('Số điện thoại đã tồn tại trong chi nhánh đã chọn.');
                        }
                    };
                })
                ->validationMessages([
                    'unique' => 'Số điện thoại đã tồn tại trong chi nhánh đã chọn.',
                ])
                ->nullable(),

            Forms\Components\TextInput::make('phone_secondary')
                ->label('Điện thoại 2')
                ->tel()
                ->maxLength(20)
                ->nullable(),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->rule(function ($record): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        if (! is_string($value) || trim($value) === '') {
                            return;
                        }

                        $emailHash = Patient::emailSearchHash($value);
                        if ($emailHash === null) {
                            return;
                        }

                        $exists = Patient::withTrashed()
                            ->when($record?->id, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot((int) $record->id))
                            ->where('email_search_hash', $emailHash)
                            ->exists();

                        if ($exists) {
                            $fail('Email bệnh nhân đã tồn tại.');
                        }
                    };
                })
                ->nullable(),

            Forms\Components\TextInput::make('occupation')
                ->label('Nghề nghiệp')
                ->maxLength(255)
                ->nullable(),

            Forms\Components\Select::make('customer_group_id')
                ->relationship('customerGroup', 'name', fn ($query) => $query->where('is_active', true))
                ->label('Nhóm khách hàng')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('promotion_group_id')
                ->relationship('promotionGroup', 'name', fn ($query) => $query->where('is_active', true))
                ->label('Nhóm khuyến mãi')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('primary_doctor_id')
                ->options(fn (Get $get): array => app(PatientAssignmentAuthorizer::class)
                    ->assignableDoctorOptions(
                        actor: auth()->user(),
                        branchId: filled($get('first_branch_id')) ? (int) $get('first_branch_id') : null,
                    ))
                ->label('Bác sĩ phụ trách')
                ->searchable()
                ->preload()
                ->helperText('Chỉ hiển thị bác sĩ đang thuộc phạm vi chi nhánh của hồ sơ.')
                ->nullable(),

            Forms\Components\Select::make('owner_staff_id')
                ->options(fn (Get $get): array => app(PatientAssignmentAuthorizer::class)
                    ->assignableStaffOptions(
                        actor: auth()->user(),
                        branchId: filled($get('first_branch_id')) ? (int) $get('first_branch_id') : null,
                    ))
                ->label('Nhân viên phụ trách')
                ->searchable()
                ->preload()
                ->helperText('Chỉ hiển thị nhân sự thuộc phạm vi chi nhánh của hồ sơ.')
                ->nullable(),

            Forms\Components\TextInput::make('address')
                ->label('Địa chỉ')
                ->maxLength(255)
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('first_visit_reason')
                ->label('Lý do đến khám')
                ->rows(2)
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('medical_history')
                ->label('Tiền sử bệnh')
                ->rows(3)
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('note')
                ->label('Ghi chú')
                ->rows(3)
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Select::make('status')
                ->label('Trạng thái hồ sơ')
                ->options([
                    'active' => 'Đang hoạt động',
                    'inactive' => 'Tạm ngưng',
                    'blocked' => 'Khóa',
                ])
                ->default('active')
                ->required(),
        ];
    }
}
