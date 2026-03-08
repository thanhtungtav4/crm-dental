<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Thông tin cá nhân')
                            ->schema(\App\Filament\Schemas\SharedSchemas::customerProfileFields())
                            ->columns(2),

                        Section::make('Ghi chú')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Nội dung ghi chú')
                                    ->rows(3)
                                    ->placeholder('Ghi chú về khách hàng này...'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Phân loại & Trạng thái')
                            ->schema([
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
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $assignedTo = $get('assigned_to');

                                        if (! filled($assignedTo)) {
                                            return;
                                        }

                                        $isAllowed = app(PatientAssignmentAuthorizer::class)
                                            ->scopeAssignableStaff(
                                                query: \App\Models\User::query()->whereKey((int) $assignedTo),
                                                actor: auth()->user(),
                                                branchId: filled($state) ? (int) $state : null,
                                            )
                                            ->exists();

                                        if (! $isAllowed) {
                                            $set('assigned_to', null);
                                        }
                                    })
                                    ->required(),

                                Forms\Components\Select::make('source')
                                    ->label('Nguồn')
                                    ->options(fn (): array => ClinicRuntimeSettings::customerSourceOptions())
                                    ->default(fn (): string => ClinicRuntimeSettings::defaultCustomerSource()),

                                Forms\Components\Select::make('customer_group_id')
                                    ->relationship('customerGroup', 'name', fn ($query) => $query->where('is_active', true))
                                    ->label('Nhóm khách hàng')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('promotion_group_id')
                                    ->relationship('promotionGroup', 'name', fn ($query) => $query->where('is_active', true))
                                    ->label('Nhóm khuyến mãi')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('status')
                                    ->label('Trạng thái')
                                    ->options(fn (): array => ClinicRuntimeSettings::customerStatusOptions())
                                    ->default(fn (): string => ClinicRuntimeSettings::defaultCustomerStatus())
                                    ->required(),

                                Forms\Components\Select::make('assigned_to')
                                    ->options(fn (Get $get): array => app(PatientAssignmentAuthorizer::class)
                                        ->assignableStaffOptions(
                                            actor: auth()->user(),
                                            branchId: filled($get('branch_id')) ? (int) $get('branch_id') : null,
                                        ))
                                    ->label('Phụ trách')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Chỉ hiển thị nhân sự thuộc phạm vi chi nhánh đang chọn.'),

                                Forms\Components\DateTimePicker::make('next_follow_up_at')
                                    ->label('Lịch hẹn gọi lại')
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}
