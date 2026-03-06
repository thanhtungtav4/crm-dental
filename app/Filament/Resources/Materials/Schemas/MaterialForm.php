<?php

namespace App\Filament\Resources\Materials\Schemas;

use App\Services\InventorySelectionAuthorizer;
use App\Support\BranchAccess;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class MaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin cơ bản')
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where(
                                    'branch_id',
                                    $get('branch_id'),
                                ),
                            )
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                            ->placeholder('VD: MAT-001')
                            ->helperText('Mã vật tư duy nhất trong từng chi nhánh')
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('Tên vật tư')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('VD: Composite resin A2')
                            ->columnSpan(1),
                        Select::make('category')
                            ->label('Danh mục')
                            ->required()
                            ->options([
                                'medicine' => '💊 Thuốc',
                                'consumable' => '📦 Vật tư tiêu hao',
                                'equipment' => '🔧 Thiết bị',
                                'dental_material' => '🦷 Vật liệu nha khoa',
                            ])
                            ->default('consumable')
                            ->searchable()
                            ->native(false)
                            ->helperText('Chọn danh mục phù hợp với vật tư')
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
                            ->default(fn (): ?int => BranchAccess::defaultBranchIdForCurrentUser())
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Thông tin nhà sản xuất & nhà cung cấp')
                    ->schema([
                        TextInput::make('manufacturer')
                            ->label('Nhà sản xuất')
                            ->maxLength(255)
                            ->placeholder('VD: 3M ESPE, Dentsply Sirona')
                            ->helperText('Tên nhà sản xuất vật tư')
                            ->columnSpan(1),
                        Select::make('supplier_id')
                            ->label('Nhà cung cấp mặc định')
                            ->relationship(
                                name: 'supplier',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => app(InventorySelectionAuthorizer::class)->scopeActiveSuppliers($query),
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label('Tên NCC')->required(),
                                TextInput::make('code')->label('Mã NCC'),
                                TextInput::make('phone')->label('Số điện thoại')->tel(),
                                TextInput::make('email')->label('Email')->email(),
                            ])
                            ->helperText('Nhà cung cấp chính cho vật tư này')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Tồn kho & Giá')
                    ->schema([
                        TextInput::make('unit')
                            ->label('Đơn vị tính')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('VD: Hộp, Lọ, Cái, Gam')
                            ->columnSpan(1),
                        TextInput::make('stock_qty')
                            ->label('Số lượng tồn kho')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Tồn tổng được đồng bộ qua lô vật tư và service nghiệp vụ, không sửa tay ở form này.')
                            ->columnSpan(1),
                        TextInput::make('min_stock')
                            ->label('Tồn kho tối thiểu')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Cảnh báo khi tồn kho <= giá trị này')
                            ->columnSpan(1),
                        TextInput::make('reorder_point')
                            ->label('Điểm đặt hàng lại')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Tự động tạo đơn đặt hàng khi tồn kho <= giá trị này')
                            ->columnSpan(1),
                        TextInput::make('cost_price')
                            ->label('Giá nhập (trung bình)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('VNĐ')
                            ->helperText('Giá nhập vốn bình quân')
                            ->columnSpan(1),
                        TextInput::make('sale_price')
                            ->label('Giá bán')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('VNĐ')
                            ->helperText('Giá bán cho bệnh nhân (hoặc tính vào dịch vụ)')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Vị trí lưu trữ & Ghi chú')
                    ->schema([
                        TextInput::make('storage_location')
                            ->label('Vị trí lưu trữ')
                            ->maxLength(255)
                            ->placeholder('VD: Tủ A, Kệ 2, Ngăn 3')
                            ->helperText('Vị trí lưu trữ trong kho')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Thông tin hệ thống')
                    ->schema([
                        Placeholder::make('total_batch_quantity')
                            ->label('Tổng số lượng các lô')
                            ->content(fn ($record) => $record?->getTotalBatchQuantity() ?? 0)
                            ->columnSpan(1),
                        Placeholder::make('active_batches_count')
                            ->label('Số lô đang hoạt động')
                            ->content(fn ($record) => $record?->batches()->where('status', 'active')->count() ?? 0)
                            ->columnSpan(1),
                        Placeholder::make('expiring_batches_count')
                            ->label('Số lô sắp hết hạn')
                            ->content(function ($record) {
                                if (! $record) {
                                    return 0;
                                }

                                $count = $record->getExpiringBatchesCount(30);

                                return $count > 0 ? "⚠️ {$count} lô" : '✅ Không có';
                            })
                            ->columnSpan(1),
                        Placeholder::make('is_low_stock')
                            ->label('Cảnh báo tồn kho')
                            ->content(function ($record) {
                                if (! $record) {
                                    return 'Chưa có';
                                }
                                if ($record->needsReorder()) {
                                    return '🔴 Cần đặt hàng ngay';
                                }
                                if ($record->isLowStock()) {
                                    return '⚠️ Tồn kho thấp';
                                }

                                return '✅ Đủ hàng';
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn ($record) => $record !== null),
            ]);
    }
}
