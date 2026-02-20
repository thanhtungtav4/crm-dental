<?php

namespace App\Filament\Resources\Appointments\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

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
                    ->getSearchResultsUsing(function (string $search): array {
                        return \App\Models\Customer::query()
                            ->where(function ($q) use ($search) {
                                $q->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->orderBy('full_name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($c) {
                                $phone = $c->phone ? " — {$c->phone}" : '';
                                $status = $c->status ? " [{$c->status}]" : '';
                                return [$c->id => $c->full_name . $phone . $status];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (!$value)
                            return null;

                        $c = \App\Models\Customer::find($value);
                        if (!$c)
                            return null;

                        $phone = $c->phone ? " — {$c->phone}" : '';
                        $status = $c->status ? " [{$c->status}]" : '';
                        return $c->full_name . $phone . $status;
                    })
                    ->afterStateHydrated(function ($state, $set, $record) {
                        // Khi load form edit, nếu có patient mà không có customer_id
                        // thì tự động fill customer_id từ patient
                        if ($record && $record->patient_id && !$state) {
                            $customerId = $record->patient?->customer_id;
                            if ($customerId) {
                                $set('customer_id', $customerId);
                            }
                        }
                    })
                    ->createOptionForm([
                        Section::make('Thông tin khách hàng')
                            ->schema(\App\Filament\Schemas\SharedSchemas::customerProfileFields())
                            ->columns(2),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        // Chỉ tạo Customer (Lead)
                        $customer = \App\Models\Customer::create([
                            'full_name' => $data['full_name'],
                            'phone' => $data['phone'],
                            'email' => $data['email'] ?? null,
                            'address' => $data['address'] ?? null,
                            'gender' => $data['gender'] ?? 'male',
                            'birthday' => $data['birthday'] ?? null,
                            'source' => 'appointment',
                            'status' => 'lead',
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

                Forms\Components\Select::make('doctor_id')
                    ->label('Bác sĩ')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return \App\Models\User::role('Doctor')
                            ->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($u) {
                                $spec = $u->specialty ? " — {$u->specialty}" : '';
                                $phone = $u->phone ? " — {$u->phone}" : '';
                                return [$u->id => $u->name . $spec . $phone];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $u = $value ? \App\Models\User::find($value) : null;
                        if (!$u)
                            return null;
                        $spec = $u->specialty ? " — {$u->specialty}" : '';
                        $phone = $u->phone ? " — {$u->phone}" : '';
                        return $u->name . $spec . $phone;
                    }),

                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Chi nhánh')
                    ->searchable()
                    ->preload()
                    ->default(fn() => auth()->user()?->branch_id),

                Forms\Components\DateTimePicker::make('date')
                    ->label('Thời gian')
                    ->required()
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i'),

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
                    ->options([
                        'scheduled' => 'Đã đặt',
                        'confirmed' => 'Đã xác nhận',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Hủy',
                    ])
                    ->default('scheduled')
                    ->live(),

                Forms\Components\Textarea::make('cancellation_reason')
                    ->label('Lý do hủy lịch')
                    ->rows(2)
                    ->visible(fn(callable $get) => $get('status') === 'cancelled')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('reschedule_reason')
                    ->label('Lý do đổi lịch')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('confirmed_at')
                    ->label('Thời gian xác nhận')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn(callable $get) => in_array($get('status'), ['confirmed', 'completed']))
                    ->helperText('Tự động ghi nhận khi chuyển sang trạng thái "Đã xác nhận"'),

                Forms\Components\Select::make('confirmed_by')
                    ->label('Người xác nhận')
                    ->relationship('confirmedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn(callable $get) => in_array($get('status'), ['confirmed', 'completed']))
                    ->default(fn(callable $get) => $get('status') === 'confirmed' ? auth()->id() : null),

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
