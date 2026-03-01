<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\DoctorBranchAssignmentService;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Khách hàng / Lead')
                    ->searchable()
                    ->preload()
                    ->default(function (): ?int {
                        $patientId = request()->integer('patient_id');

                        if (! $patientId) {
                            return null;
                        }

                        return Patient::query()
                            ->whereKey($patientId)
                            ->value('customer_id');
                    })
                    ->getSearchResultsUsing(function (string $search): array {
                        $query = \App\Models\Customer::query()
                            ->where(function ($q) use ($search) {
                                $q->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });

                        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');

                        return $query
                            ->orderBy('full_name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($c) {
                                $phone = $c->phone ? " — {$c->phone}" : '';
                                $status = $c->status ? " [{$c->status}]" : '';

                                return [$c->id => $c->full_name.$phone.$status];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! $value) {
                            return null;
                        }

                        $query = \App\Models\Customer::query()
                            ->whereKey((int) $value);

                        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');

                        $c = $query->first();
                        if (! $c) {
                            return null;
                        }

                        $phone = $c->phone ? " — {$c->phone}" : '';
                        $status = $c->status ? " [{$c->status}]" : '';

                        return $c->full_name.$phone.$status;
                    })
                    ->afterStateHydrated(function ($state, $set, $record) {
                        // Khi load form edit, nếu có patient mà không có customer_id
                        // thì tự động fill customer_id từ patient
                        if ($record && $record->patient_id && ! $state) {
                            $customerId = $record->patient?->customer_id;
                            if ($customerId) {
                                $set('customer_id', $customerId);
                            }
                        }

                        if (! $record && ! $state) {
                            $patientId = request()->integer('patient_id');
                            if (! $patientId) {
                                return;
                            }

                            $patient = Patient::query()
                                ->find($patientId, ['id', 'customer_id']);

                            if (! $patient) {
                                return;
                            }

                            $set('patient_id', $patient->id);
                            if ($patient->customer_id) {
                                $set('customer_id', $patient->customer_id);
                            }
                        }
                    })
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (blank($state)) {
                            $set('patient_id', null);

                            return;
                        }

                        $patientId = Patient::query()
                            ->where('customer_id', $state)
                            ->value('id');

                        $set('patient_id', $patientId);
                    })
                    ->createOptionForm([
                        Section::make('Thông tin khách hàng')
                            ->schema(\App\Filament\Schemas\SharedSchemas::customerProfileFields())
                            ->columns(2),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        $branchId = BranchAccess::defaultBranchIdForCurrentUser();

                        // Chỉ tạo Customer (Lead)
                        $customer = \App\Models\Customer::create([
                            'branch_id' => $branchId,
                            'full_name' => $data['full_name'],
                            'phone' => $data['phone'],
                            'email' => $data['email'] ?? null,
                            'address' => $data['address'] ?? null,
                            'gender' => $data['gender'] ?? array_key_first(ClinicRuntimeSettings::genderOptions()) ?? 'male',
                            'birthday' => $data['birthday'] ?? null,
                            'source' => array_key_exists('appointment', ClinicRuntimeSettings::customerSourceOptions())
                                ? 'appointment'
                                : ClinicRuntimeSettings::defaultCustomerSource(),
                            'status' => ClinicRuntimeSettings::defaultCustomerStatus(),
                            'assigned_to' => auth()->id(),
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Đã tạo nguồn lead mới thành công!')
                            ->success()
                            ->send();

                        return $customer->id;
                    })
                    ->createOptionModalHeading('Tạo khách hàng / Lead mới')
                    ->required(),

                Forms\Components\Hidden::make('patient_id')
                    ->default(fn (): ?int => request()->integer('patient_id') ?: null),

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
                    ->getOptionLabelUsing(fn ($value): ?string => $value ? \App\Models\User::query()->whereKey($value)->value('name') : null)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Chỉ hiển thị bác sĩ đã được phân công theo chi nhánh và lịch hiệu lực.'),

                Forms\Components\Select::make('branch_id')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->label('Chi nhánh')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => BranchAccess::defaultBranchIdForCurrentUser())
                    ->required()
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
                    }),

                Forms\Components\DateTimePicker::make('date')
                    ->label('Thời gian')
                    ->required()
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
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
                    }),

                Forms\Components\Select::make('appointment_type')
                    ->label('Loại lịch hẹn')
                    ->options([
                        'consultation' => 'Tư vấn',
                        'treatment' => 'Điều trị',
                        'follow_up' => 'Tái khám',
                        'emergency' => 'Khẩn cấp',
                    ])
                    ->default('consultation')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Auto-set duration based on appointment type
                        $durations = [
                            'consultation' => 30,
                            'treatment' => 60,
                            'follow_up' => 20,
                            'emergency' => 45,
                        ];
                        $set('duration_minutes', $durations[$state] ?? 30);
                    }),

                Forms\Components\Select::make('appointment_kind')
                    ->label('Phân loại cuộc hẹn')
                    ->options([
                        'booking' => 'Đặt hẹn',
                        're_exam' => 'Tái khám',
                    ])
                    ->default('booking')
                    ->required(),

                Forms\Components\TextInput::make('duration_minutes')
                    ->label('Thời lượng (phút)')
                    ->numeric()
                    ->required()
                    ->minValue(5)
                    ->maxValue(480)
                    ->default(30)
                    ->suffix('phút')
                    ->helperText('Thời gian dự kiến cho cuộc hẹn'),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(Appointment::statusOptions())
                    ->default(Appointment::STATUS_SCHEDULED)
                    ->required()
                    ->live(),

                Forms\Components\Textarea::make('cancellation_reason')
                    ->label('Lý do hủy lịch')
                    ->rows(2)
                    ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                    ->required(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('reschedule_reason')
                    ->label('Lý do đổi lịch')
                    ->rows(2)
                    ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                    ->required(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('overbooking_reason')
                    ->label('Lý do overbooking')
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Nhập khi muốn giữ lịch hẹn vượt quá công suất theo policy chi nhánh.'),

                Forms\Components\Hidden::make('overbooking_override_by')
                    ->default(fn () => auth()->id()),

                Forms\Components\DateTimePicker::make('confirmed_at')
                    ->label('Thời gian xác nhận')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn (callable $get) => in_array($get('status'), Appointment::statusesRequiringConfirmation(), true))
                    ->helperText('Tự động ghi nhận khi chuyển sang trạng thái "Đã xác nhận"'),

                Forms\Components\Select::make('confirmed_by')
                    ->label('Người xác nhận')
                    ->relationship('confirmedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => in_array($get('status'), Appointment::statusesRequiringConfirmation(), true))
                    ->default(fn (callable $get) => in_array($get('status'), Appointment::statusesRequiringConfirmation(), true) ? auth()->id() : null),

                Forms\Components\Textarea::make('chief_complaint')
                    ->label('Lý do khám')
                    ->rows(2)
                    ->columnSpanFull()
                    ->placeholder('Mô tả triệu chứng, vấn đề mà bệnh nhân gặp phải...')
                    ->helperText('Ghi rõ lý do khám, triệu chứng chính của bệnh nhân'),

                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->columnSpanFull()
                    ->placeholder('Các ghi chú khác về lịch hẹn...'),
            ]);
    }
}
