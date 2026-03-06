<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Services\UserProvisioningAuthorizer;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $authorizer = app(UserProvisioningAuthorizer::class);

        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->relationship(
                        'branch',
                        'name',
                        fn (Builder $query): Builder => $authorizer->scopeAssignableBranches($query, auth()->user()),
                    )
                    ->label('Chi nhánh')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('doctor_branch_ids')
                    ->label('Chi nhánh làm việc (Bác sĩ)')
                    ->options(fn (): array => $authorizer->assignableBranchOptions(auth()->user()))
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
                    ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
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

                Forms\Components\FileUpload::make('avatar_url')
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
                    ->relationship(
                        'roles',
                        'name',
                        fn (Builder $query): Builder => $authorizer->scopeAssignableRoles($query, auth()->user()),
                    )
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(3)
                    ->visible(fn (): bool => $authorizer->canManageRoles(auth()->user()))
                    ->helperText('Chỉ Admin được phép gán vai trò cho người dùng.')
                    ->columnSpanFull(),

                Forms\Components\CheckboxList::make('permissions')
                    ->label('Quyền')
                    ->relationship(
                        'permissions',
                        'name',
                        fn (Builder $query): Builder => $authorizer->scopeAssignablePermissions($query, auth()->user()),
                    )
                    ->options(fn (): array => $authorizer->assignablePermissionOptions(auth()->user()))
                    ->columns(3)
                    ->bulkToggleable()
                    ->visible(fn (): bool => $authorizer->canManageDirectPermissions(auth()->user()))
                    ->helperText('Chỉ Admin được phép gán quyền trực tiếp. Ưu tiên quản trị bằng vai trò.')
                    ->columnSpanFull(),
            ]);
    }
}
