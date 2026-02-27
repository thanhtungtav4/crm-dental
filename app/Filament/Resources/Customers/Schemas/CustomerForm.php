<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                    ->relationship('branch', 'name')
                                    ->label('Chi nhánh')
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => auth()->user()?->branch_id)
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
                                    ->relationship('assignee', 'name')
                                    ->label('Phụ trách')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\DateTimePicker::make('next_follow_up_at')
                                    ->label('Lịch hẹn gọi lại')
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}
