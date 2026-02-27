<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Chi nhánh')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('doctor_branch_ids')
                    ->label('Chi nhánh làm việc (Bác sĩ)')
                    ->options(fn (): array => \App\Models\Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Forms\Components\Select $component, $state, $record): void {
                        if (! $record || filled($state)) {
                            return;
                        }

                        $branchIds = $record->activeDoctorBranchAssignments()
                            ->pluck('branch_id')
                            ->map(fn ($branchId): int => (int) $branchId)
                            ->values()
                            ->all();

                        if ($branchIds === [] && filled($record->branch_id)) {
                            $branchIds = [(int) $record->branch_id];
                        }

                        $component->state($branchIds);
                    })
                    ->helperText('Dùng cho user có vai trò Bác sĩ. Có thể phân công 1 bác sĩ tại nhiều chi nhánh.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('Tên')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->unique(table: 'users', column: 'email', ignoreRecord: true)
                    ->required(),

                Forms\Components\TextInput::make('phone')
                    ->label('Điện thoại')
                    ->tel()
                    ->maxLength(20)
                    ->nullable(),

                Forms\Components\TextInput::make('specialty')
                    ->label('Chuyên môn')
                    ->maxLength(255)
                    ->helperText('Ví dụ: Cấy ghép implant, Chỉnh nha, Phục hình, Nha chu...')
                    ->nullable(),

                Forms\Components\Select::make('gender')
                    ->label('Giới tính')
                    ->options([
                        'male' => 'Nam',
                        'female' => 'Nữ',
                        'other' => 'Khác',
                    ])
                    ->nullable(),

                Forms\Components\TextInput::make('password')
                    ->label('Mật khẩu')
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('avatar')
                    ->label('Avatar')
                    ->image()
                    ->disk('public')
                    ->directory('avatars')
                    ->visibility('public')
                    ->nullable(),

                Forms\Components\Toggle::make('status')
                    ->label('Kích hoạt')
                    ->default(true)
                    ->inline(false)
                    ->columnSpanFull(),

                Forms\Components\CheckboxList::make('roles')
                    ->label('Vai trò')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(3)
                    ->columnSpanFull(),

                Forms\Components\CheckboxList::make('permissions')
                    ->label('Quyền')
                    ->relationship('permissions', 'name')
                    ->options(function () {
                        $options = [];
                        $perms = Permission::query()->orderBy('name')->get();
                        foreach ($perms as $perm) {
                            // Expecting pattern: prefix_resource, e.g., view_any_customer
                            $parts = explode('_', $perm->name, 2);
                            $resource = $parts[1] ?? 'other';
                            $group = match ($resource) {
                                'user' => 'Người dùng',
                                'branch' => 'Chi nhánh',
                                'customer' => 'Khách hàng',
                                'patient' => 'Bệnh nhân',
                                'treatment-plan' => 'Kế hoạch điều trị',
                                'treatment-session' => 'Phiên điều trị',
                                'plan-item' => 'Hạng mục điều trị',
                                'material' => 'Vật tư',
                                'treatment-material' => 'Vật tư sử dụng',
                                'invoice' => 'Hóa đơn',
                                'payment' => 'Thanh toán',
                                'note' => 'Ghi chú',
                                'appointment' => 'Lịch hẹn',
                                default => 'Khác',
                            };
                            $label = str_replace(['_', '-'], ' ', $perm->name);
                            $options[$perm->id] = "$group — ".ucwords($label);
                        }

                        return $options;
                    })
                    ->columns(3)
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }
}
