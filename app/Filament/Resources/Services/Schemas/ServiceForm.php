<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Support\BranchAccess;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin cơ bản')
                    ->schema([
                        Select::make('category_id')
                            ->label('Danh mục dịch vụ')
                            ->relationship('category', 'name', fn ($query) => $query->where('active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('Tên dịch vụ')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('code')
                            ->label('Mã dịch vụ')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        Textarea::make('description')
                            ->label('Mô tả chi tiết')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Giá & Thời gian')
                    ->schema([
                        TextInput::make('default_price')
                            ->label('Đơn giá (VNĐ)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('VNĐ')
                            ->columnSpan(1),
                        TextInput::make('unit')
                            ->label('Đơn vị tính')
                            ->default('lần')
                            ->maxLength(50)
                            ->columnSpan(1),
                        TextInput::make('duration_minutes')
                            ->label('Thời lượng')
                            ->required()
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(480)
                            ->default(30)
                            ->suffix('phút')
                            ->columnSpan(1),
                        TextInput::make('doctor_commission_rate')
                            ->label('Tỷ lệ hoa hồng bác sĩ')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(15)
                            ->suffix('%')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Cài đặt nâng cao')
                    ->schema([
                        Toggle::make('tooth_specific')
                            ->label('Dịch vụ theo răng cụ thể')
                            ->helperText('Bật nếu dịch vụ này áp dụng cho từng răng riêng lẻ (vd: trám răng, nhổ răng)')
                            ->default(false)
                            ->columnSpan(1),
                        Toggle::make('requires_consent')
                            ->label('Bắt buộc consent trước điều trị')
                            ->helperText('Dùng cho thủ thuật rủi ro cao (implant, phẫu thuật...).')
                            ->default(false)
                            ->columnSpan(1),
                        Toggle::make('active')
                            ->label('Kích hoạt')
                            ->default(true)
                            ->columnSpan(1),
                        Select::make('branch_id')
                            ->label('Chi nhánh')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Để trống nếu dịch vụ áp dụng cho tất cả chi nhánh')
                            ->columnSpan(1),
                        TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
