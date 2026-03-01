<?php

namespace App\Filament\Resources\FactoryOrders\Schemas;

use App\Models\FactoryOrder;
use App\Support\BranchAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FactoryOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Bệnh nhân')
                    ->relationship(
                        name: 'patient',
                        titleAttribute: 'full_name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query, 'first_branch_id'),
                    )
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => request()->integer('patient_id') ?: null)
                    ->required()
                    ->live(),

                Select::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn (): ?int => request()->integer('branch_id') ?: BranchAccess::defaultBranchIdForCurrentUser()),

                Select::make('doctor_id')
                    ->label('Bác sĩ phụ trách')
                    ->relationship('doctor', 'name', fn (Builder $query): Builder => $query->role('Doctor'))
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => request()->integer('doctor_id') ?: null)
                    ->nullable(),

                TextInput::make('order_no')
                    ->label('Mã lệnh labo')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Mã tự động sinh khi lưu.')
                    ->visibleOn('edit'),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options(FactoryOrder::statusOptions())
                    ->default(FactoryOrder::STATUS_DRAFT)
                    ->required(),

                Select::make('priority')
                    ->label('Ưu tiên')
                    ->options([
                        'low' => 'Thấp',
                        'normal' => 'Bình thường',
                        'high' => 'Cao',
                        'urgent' => 'Khẩn',
                    ])
                    ->default('normal')
                    ->required(),

                TextInput::make('vendor_name')
                    ->label('Labo/Nhà cung cấp')
                    ->maxLength(255),

                DateTimePicker::make('ordered_at')
                    ->label('Ngày đặt')
                    ->native(false)
                    ->seconds(false)
                    ->default(now()),

                DateTimePicker::make('due_at')
                    ->label('Ngày hẹn trả')
                    ->native(false)
                    ->seconds(false),

                Textarea::make('notes')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
