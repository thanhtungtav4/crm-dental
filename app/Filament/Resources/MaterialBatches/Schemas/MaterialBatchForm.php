<?php

namespace App\Filament\Resources\MaterialBatches\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MaterialBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin vật tư & lô hàng')
                    ->schema([
                        Select::make('material_id')
                            ->label('Vật tư')
                            ->relationship('material', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('batch_number')
                            ->label('Số lô')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('VD: LOT-2024-001')
                            ->helperText('Số lô sản xuất từ nhà cung cấp')
                            ->columnSpan(1),
                        Select::make('supplier_id')
                            ->label('Nhà cung cấp')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'active' => 'Đang sử dụng',
                                'expired' => 'Đã hết hạn',
                                'recalled' => 'Thu hồi',
                                'depleted' => 'Đã hết',
                            ])
                            ->default('active')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('⚠️ Thông tin quan trọng - Hạn sử dụng & Số lượng')
                    ->description('Theo dõi chặt chẽ để đảm bảo an toàn cho bệnh nhân')
                    ->schema([
                        DatePicker::make('expiry_date')
                            ->label('🚨 Hạn sử dụng (HSD)')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now())
                            ->helperText('Ngày hết hạn sử dụng - HỆ THỐNG SẼ CẢNH BÁO KHI SẮP HẾT HẠN')
                            ->columnSpan(1),
                        DatePicker::make('received_date')
                            ->label('Ngày nhận hàng')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->default(now())
                            ->columnSpan(1),
                        TextInput::make('quantity')
                            ->label('Số lượng')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Số lượng hiện có trong lô này')
                            ->columnSpan(1),
                        TextInput::make('purchase_price')
                            ->label('Giá nhập')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('VNĐ')
                            ->helperText('Giá nhập của lô hàng này')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Ghi chú')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Ghi chú về lô hàng, điều kiện bảo quản đặc biệt...'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Thông tin hệ thống')
                    ->schema([
                        Placeholder::make('expiry_warning')
                            ->label('Cảnh báo hết hạn')
                            ->content(function ($record) {
                                if (!$record) return 'Chưa có';
                                $warning = $record->getExpiryWarningMessage();
                                return $warning ?? '✅ Còn hạn sử dụng';
                            })
                            ->columnSpan(1),
                        Placeholder::make('days_until_expiry')
                            ->label('Số ngày còn lại')
                            ->content(function ($record) {
                                if (!$record) return 'Chưa có';
                                $days = $record->getDaysUntilExpiry();
                                if ($days < 0) return 'Đã hết hạn ' . abs($days) . ' ngày';
                                return $days . ' ngày';
                            })
                            ->columnSpan(1),
                        Placeholder::make('created_by_info')
                            ->label('Người tạo')
                            ->content(fn ($record) => $record?->createdBy?->name ?? 'Chưa có')
                            ->columnSpan(1),
                        Placeholder::make('created_at')
                            ->label('Ngày tạo')
                            ->content(fn ($record) => $record?->created_at?->format('d/m/Y H:i') ?? 'Chưa có')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn ($record) => $record !== null),
            ]);
    }
}
